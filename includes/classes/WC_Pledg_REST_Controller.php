<?php

use Firebase\JWT\JWT;

/**
 * Class WC_Pledg_API_Handler file.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook mainly called by notificationUrl.
 *
 * @since 2.0.0
 */
class WC_Webhook_REST_Controller extends WP_REST_Controller
{
    public const CASE_SIGNED_TRANSFER = 1;
    public const CASE_SIGNED_BACK = 2;
    public const CASE_UNSIGNED_TRANSFER = 3;

    public const HTTP_OK = 200;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_FORBIDDEN = 403;

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    public static $namespaceStatic = 'pledg/v2';

    public function __construct()
    {
        $this->namespace = WC_Webhook_REST_Controller::$namespaceStatic;
    }

    /**
     * Returns the webhook to call from order_id.
     *
     * @param integer $order_id
     * @return string
     */
    public static function get_order_webhook_from_id($order_id)
    {
        return rest_url(WC_Webhook_REST_Controller::$namespaceStatic . "/order/{$order_id}/");
    }

    /**
     * Returns the webhook to call in case of error after the payment screen.
     *
     * @param integer $order_id
     * @return string
     */
    public static function get_order_error_webhook_from_id($order_id)
    {
        return rest_url(WC_Webhook_REST_Controller::$namespaceStatic . "/order/{$order_id}/error");
    }

    /**
     * Registers rest routes
     */
    public function register_routes()
    {
        add_action('rest_api_init', function () {
            register_rest_route($this->namespace, '/' . 'order/(?P<getId>\d+)', [
              'methods' => 'POST',
              'callback' => [$this, 'check_response'],
              'permission_callback' => '__return_true'
            ]);
        });
        add_action('rest_api_init', function () {
            register_rest_route($this->namespace, '/' . 'order/(?P<getId>\d+)/error', [
              'methods' => 'POST',
              'callback' => [$this, 'handle_error'],
              'permission_callback' => '__return_true'
            ]);
        });
    }

    /**
     * Handle the error notification from Pledg in case of payment problem
     *
     * @return WP_REST_Response
     */
    public function handle_error(WP_REST_Request $request)
    {
        $logger = wc_get_logger();
        $params = (object) $request->get_params();
        $order_id = $params->getId;

        $order = wc_get_order($params->getId);

        $response = new WP_REST_Response();
        $response->set_headers(['Cache-Control' => 'must-revalidate, no-cache, no-store, private']);

        // we check that there is an order to match the getId
        if (!$order) {
            $message = sprintf(
                __('Webhook called but order id didn\'t match any order : %s', 'woocommerce-pledg'),
                json_encode($request->get_params())
            );

            $logger->error($message, [ 'source' => 'pledg_woocommerce_webhook' ]);
            $response->set_data(['state' => 'Error', 'message' => $message]);
            $response->set_status(self::HTTP_BAD_REQUEST);
            return $response;
        }

        // Signature case
        if (isset($params->signature)) {
            $secret = WC()->payment_gateways->payment_gateways()[$order->get_payment_method()]->get_option('secret_key');
            try {
                $params = JWT::decode($params->signature, $secret, ['HS256']);
            } catch (Exception $e) {
                $message = sprintf(
                    __('Signature could not be decoded for the order %s : %s', 'woocommerce-pledg'),
                    $order_id,
                    $e->getMessage()
                )
                ;

                $logger->error($message, ['source' => 'pledg_woocommerce_webhook']);
                $response->set_data(['state' => 'Error', 'message' => $message]);
                $response->set_status(self::HTTP_BAD_REQUEST);
                return $response;
            }
        }

        // we check that the reference posted is equal to the order id
        $order_id_from_ref = $this->get_ID_from_reference($params->reference);
        if ($order_id_from_ref !== $order_id) {
            $message = sprintf(
                __('Webhook called but the reference didn\'t match the order id %s (wrong webhook called with this payload) : %s', 'woocommerce-pledg'),
                json_encode($order_id),
                json_encode($params)
            );

            $logger->error($message, ['source' => 'pledg_woocommerce_webhook']);
            $response->set_data(['state' => 'Error', 'message' => $message]);
            $response->set_status(self::HTTP_BAD_REQUEST);
            return $response;
        }

        $errorStatus = array_key_exists($params->state, WC_Pledg_Constants::PLEDG_ERROR_CODES)
            ? WC_Pledg_Constants::PLEDG_ERROR_CODES[$params->state] : WC_Pledg_Constants::PLEDG_ERROR_CODES['DEFAULT'];
        $order->add_order_note(sprintf(
            __('%s has notified an error for the payment of this order. Status : %s', 'woocommerce-pledg'),
            PLEDG_OPERATOR,
            __($errorStatus)
        ));

        $data = [
            'state' => 'Ok',
            'id' => $order->get_id(),
            'order' => $order->get_order_number(),
            'transaction_id' => $order->get_transaction_id(),
            'order_status' => $order->get_status(),
            'webhook' => $this->get_order_webhook_from_id($order->get_id())
        ];
        $response->set_data($data);
        $response->set_status(self::HTTP_OK);

        return $response;
    }

    /**
     * Check for Pledg Response.
     */
    public function check_response(WP_REST_Request $request)
    {
        $case = $this->get_case($request->get_params());

        if (is_array($case) && $case['error'] == true) {
            $data = [ 'state' => 'Error', 'message' => $case['message'] ];
            $html = self::HTTP_FORBIDDEN;
        } else {
            $stillValid = false;
            switch ($case) {
                case self::CASE_SIGNED_TRANSFER:
                    $order_id = $request->get_param('getId');
                    $order = wc_get_order($order_id);
                    $check = $this->decode_transfer($order, $request->get_params());
                    $ref = $check[1]->reference;
                    $transaction_uid = $check[1]->transfer_order_item_uid;
                    $stillValid = $check[0];
                    break;
                case self::CASE_SIGNED_BACK:
                    $order_id = $request->get_param('getId');
                    $ref = $request->get_param('reference');
                    $transaction_uid = $request->get_param('id');
                    $order = wc_get_order($order_id);
                    $stillValid = $this->check_signature_back($order, $request->get_params());
                    break;
                case self::CASE_UNSIGNED_TRANSFER:
                    $order_id = json_decode($request->get_param('getId'));
                    $ref = $request->get_param('reference');
                    $transaction_uid = $request->get_param('transfer_order_item_uid');
                    $order = wc_get_order($order_id);
                    $stillValid = true;
                    break;
            }
            if ($stillValid) {
                $order->add_order_note(sprintf(__('%s has notified this order as paid.', 'woocommerce-pledg'), PLEDG_OPERATOR));
                // We save in order note the transaction ID
                $order->add_order_note('Transaction ID : ' . $transaction_uid);
                $this->payment_complete($order, $ref);
                $data = [
                    'state' => 'Ok',
                    'case' => $case,
                    'id' => $order->get_id(),
                    'order' => $order->get_order_number(),
                    'transaction_id' => $order->get_transaction_id(),
                    'order_status' => $order->get_status(),
                    'webhook' => $this->get_order_webhook_from_id($order->get_id())
                ];
                $html = self::HTTP_OK;
            } else {
                $data = ['state' => 'Error', 'message' => 'Decoding the message thrown an error.'];
                $html = self::HTTP_FORBIDDEN;
            }
        }

        $response = new WP_REST_Response($data, $html);
        $response->set_headers([ 'Cache-Control' => 'must-revalidate, no-cache, no-store, private' ]);

        return $response;
    }

    /**
     * Check Pledg Webhook validity.
     * Returns either false if there is a mismatch or an error
     * Or an integer depending on the case we are :
     * CASE_SIGNED_TRANSFER
     * CASE_SIGNED_BACK
     * CASE_UNSIGNED_TRANSFER
     * @return bool|int
     */
    public function get_case($params)
    {
        $logger = wc_get_logger();

        // we check that there is an order to match the getId
        $order = wc_get_order($params['getId']);
        if (!$order) {
            $msg = __('Webhook called but getId didn\'t match any order : ', 'woocommerce-pledg') . json_encode($params);
            $logger->error($msg, [ 'source' => 'pledg_woocommerce_webhook' ]);
            return [ 'error' => true, 'message' => $msg ];
        }

        // we check that there is a reference
        if (!isset($params['reference'])) {
            if (isset($params['signature'])) {
                // CASE OF A SIGNED TRANSFERT (only signature param)
                $logger->info(__('Webhook called in the case of a signed transfer.', 'woocommerce-pledg'), [ 'source' => 'pledg_woocommerce_webhook' ]);
                return $this::CASE_SIGNED_TRANSFER;
            }
            $msg = __('Webhook called but there was no reference : ', 'woocommerce-pledg') . json_encode($params);
            $logger->error($msg, [ 'source' => 'pledg_woocommerce_webhook' ]);
            return [ 'error' => true, 'message' => $msg ];
        }
        $order_id = $this->get_ID_from_reference($params['reference']);

        // we check that the reference posted is equal to the getId
        if ($order_id != $params['getId']) {
            $msg = __('Webhook called but the reference didn\'t match the getId (wrong webhook called with this payload) : ', 'woocommerce-pledg') . $order_id . " " . json_encode($params);
            $logger->error($msg, [ 'source' => 'pledg_woocommerce_webhook' ]);
            return [ 'error' => true, 'message' => $msg ];
        }

        // if there is a signature we're in the case of a back
        if (isset($params['signature'])) {
            $logger->info(__('Webhook called in the case of a signed back mode.', 'woocommerce-pledg'), [ 'source' => 'pledg_woocommerce_webhook' ]);
            return $this::CASE_SIGNED_BACK;
        }

        // we check that the amount_cents is equal to the order total
        $total = intval($order->get_total() * 100);
        if (!isset($params['amount_cents']) || $total != $params['amount_cents']) {
            $msg = __('Webhook called but amount_cents didn\'t match to order total : ', 'woocommerce-pledg') . json_encode($params);
            $logger->error($msg, [ 'source' => 'pledg_woocommerce_webhook' ]);
            return [ 'error' => true, 'message' => $msg ];
        } else {
            $logger->info(__('Webhook called in the case of a unsigned transfer.', 'woocommerce-pledg'), [ 'source' => 'pledg_woocommerce_webhook' ]);
            return $this::CASE_UNSIGNED_TRANSFER;
        }
    }

    /**
     * Management of the payment of an order : updating the status mainly.
     *
     * @param  WC_Order $order.
     * @param string $transaction_id Transaction ID.
     */
    protected function payment_complete(WC_Order $order, $transaction_id = '')
    {
        if (!$order->has_status([ 'processing', 'completed' ])) {
            $order->payment_complete($transaction_id);
            if (isset(WC()->cart)) {
                WC()->cart->empty_cart();
            }
        }
    }

    /**
     * Check the signature in case of a back mode
     * Return true if the signatures match, false otherwise.
     * @return bool
     */
    public function check_signature_back($order, $params)
    {
        $signatureTT = $params['signature'];
        $secret = WC()->payment_gateways->payment_gateways()[$order->get_payment_method()]->get_option('secret_key');

        //Make the string to hash
        $fields = [
            'created_at',
            'error',
            'id',
            'reference',
            'sandbox',
            'status'
        ];
        $stringTH = '';
        for ($i = 0; $i < count($fields); $i++) {
            if ($i != count($fields) - 1) {
                $stringTH .= $fields[$i] . '=' . $params[$fields[$i]] . $secret;
            } else {
                $stringTH .= $fields[$i] . '=' . $params[$fields[$i]];
            }
        }
        $hash = hash('sha256', $stringTH);
        return ((strtoupper($hash) === $signatureTT) && ($params['status'] == 'completed'));
    }

    /**
     * Decode the response in case of a transfer mode with signature
     * Return true if everything is ok and the payment has to be marked as paid, false otherwise.
     * @return bool
     */
    public function decode_transfer($order, $params)
    {
        $logger = wc_get_logger();
        $secret = WC()->payment_gateways->payment_gateways()[$order->get_payment_method()]->get_option('secret_key');
        try {
            $signatureDec = JWT::decode($params['signature'], $secret, ['HS256']);
        } catch (Exception $e) {
            $logger->error(__('Signature could not be decoded of the order ', 'woocommerce-pledg') . $params['getId'] . ' ' . $e->getMessage(), [ 'source' => 'pledg_woocommerce_webhook' ]);
            return false;
        }
        if ($this->get_ID_from_reference($signatureDec->reference) !== $params['getId']) {
            $logger->error(__('Webhook called but the reference didn\'t match the getId (wrong webhook called with this payload) : ', 'woocommerce-pledg') . json_encode($params) . ' ' . json_encode($signatureDec), [ 'source' => 'pledg_woocommerce_webhook' ]);
            return false;
        }
        $order = wc_get_order($params['getId']);
        if ($signatureDec->amount_cents != intval($order->get_total() * 100)) {
            $logger->error(__('Webhook called but amount_cents didn\'t match to order total : ', 'woocommerce-pledg') . json_encode($order->get_total() * 100) . ' ' . $signatureDec->amount_cents, [ 'source' => 'pledg_woocommerce_webhook' ]);
            return false;
        }

        return [true, $signatureDec];
    }

    /**
     * Get the ID from the reference
     * Reference is always as such : Pledg_ID_Timestamp
     */
    public function get_ID_from_reference($ref)
    {
        preg_match('/' . PLEDG_OPERATOR . '_([a-zA-Z0-9]+)_[0-9]+/', $ref, $ret);
        return $ret[1];
    }
}
