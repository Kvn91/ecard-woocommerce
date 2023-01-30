<?php

use Firebase\JWT\JWT;

/**
 * Pledg Payment Gateway
 *
 * Provides a form based Gateway for Pledg payment solution to WooCommerce
 *
 * @class       WC_Pledg_Gateway
 * @extends     WC_Payment_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Pledg_Gateway extends WC_Payment_Gateway
{
    public const ORDER_STATUS_TO_EXCLUDE = [
        'wc-cancelled',
        'wc-refunded',
        'wc-failed',
    ];

    /**
     * Minimum transaction amount, zero does not define a minimum.
     *
     * @var int
     */
    public $min_amount = 0;

    /**
     * Languages for Title and Description
     */
    public $langs = ['fr', 'en', 'de', 'es', 'it', 'nl'];

    public function __construct()
    {
        $this->icon = ($this->get_option('logo') === '') ? WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'logo.jpg' : $this->get_option('logo');
        $this->has_fields = true;
        $this->min_amount = $this->get_option('minAmount');
        $this->max_amount = $this->get_option('maxAmount');
        $this->method_title = PLEDG_OPERATOR;
        $this->method_description = (($this->get_option('description')) ? $this->get_option('description') : __('Instalment payment, simple and accessible.', 'woocommerce-pledg'));

        $this->supports = [
            'products'
        ];

        $this->init_form_fields();
        $this->init_settings();
        if (in_array(substr(get_locale(), 0, 2), $this->langs)) {
            $this->title = $this->get_option('title_' . substr(get_locale(), 0, 2));
            $this->description = $this->get_option('description_' . substr(get_locale(), 0, 2));
        } else {
            $this->title = $this->get_option('title_en');
            $this->description = $this->get_option('description_en');
        }
    }

    public function init()
    {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     *  Function to create metadata
     */
    private function create_metadata()
    {
        global $woocommerce;

        $metadata = [];
        $metadata['plugin'] = 'woocommerce-pledg-plugin' . get_file_data(
            WOOCOMMERCE_PLEDG_PLUGIN_DIR . "woocommerce-pledg.php",
            ['version' => 'version']
        )['version'];

        try {
            // Delivery
            foreach ($woocommerce->cart->get_shipping_packages() as $package_id => $package) {
                // Check if a shipping for the current package exist
                if ($woocommerce->session->__isset('shipping_for_package_' . $package_id)) {
                    // Loop through shipping rates for the current package
                    foreach ($woocommerce->session->get('shipping_for_package_' . $package_id)['rates'] as $shipping_rate_id => $shipping_rate) {
                        if ($woocommerce->session->get('chosen_shipping_methods')[0] == $shipping_rate_id) {
                            $metadata['delivery_mode'] = $shipping_rate->get_method_id() == 'local_pickup' ? 'relay' : 'home';
                            $metadata['delivery_speed'] = 0;
                            $metadata['delivery_label'] = $shipping_rate->get_label();
                            $metadata['delivery_cost'] = $shipping_rate->get_cost();
                            $metadata['delivery_tax_cost'] = $shipping_rate->get_shipping_tax();
                        }
                    }
                }
            }

            // Products
            $md_products = [];
            $md_products_count = 0;

            foreach ($woocommerce->cart->get_cart() as $cart_item) {
                // Limit export to the first 5 products (JWT signature can be too long otherwise)
                if ($md_products_count < 5) {
                    $md_product = [];

                    $product = wc_get_product($cart_item['data']->get_id());
                    $md_product['reference'] = $product->get_id();
                    $md_product['type'] = $product->get_virtual() == false ? 'physical' : 'virtual';
                    $md_product['quantity'] = $cart_item['quantity'];
                    $md_product['name'] = $product->get_name();
                    $md_product['unit_amount_cents'] = intval($cart_item['data']->get_price() * 100);
                    $md_product['category'] = '';
                    $md_product['slug'] = $product->get_slug();
                    array_push($md_products, $md_product);

                    $md_products_count++;
                } else {
                    break;
                }
            }
            $metadata['products'] = $md_products;

            $pledgSettings = get_option('pledg_plugin_options');
            $metadata['widget'] = [
                'productWidgetEnabled' => !!$pledgSettings['product_widget'],
                'cartWidgetEnabled' => !!$pledgSettings['cart_widget'],
                'paymentPreSelectionEnabled' => !!$pledgSettings['payment_pre_selected'],
                'productWidgetClicked' => isset($_COOKIE['pledg_product_widget']),
                'cartWidgetClicked' => isset($_COOKIE['pledg_cart_widget']),
            ];

            // Account
            if ($woocommerce->customer->get_id()) {
                $orderStatus = array_filter(
                    array_keys(wc_get_order_statuses()),
                    function ($item) {
                        if (!in_array($item, WC_Pledg_Gateway::ORDER_STATUS_TO_EXCLUDE)) {
                            return $item;
                        }
                    }
                );
                $metadata['account'] = [
                    'number_of_purchases' => (count(wc_get_orders([
                        'customer_id' => $woocommerce->customer->get_id(),
                        'status' => $orderStatus,
                    ])) - 1),
                ];
                if ($woocommerce->customer->get_date_created()) {
                    $metadata['account']['creation_date'] = $woocommerce->customer->get_date_created()->format('Y-m-d');
                }
            }
        } catch (Exception $exp) {
            wc_get_logger()->error('pledg_create_metadata - exception : ' . $exp->getMessage(), ['source' => 'pledg_woocommerce']);
        }
        return $metadata;
    }

    /**
     * Function to declare admin options
     */
    public function init_form_fields()
    {
        global $woocommerce;
        $lang = $this->langs;

        $a_titles = [
            'title_lang' => [
                'title' => __('Title lang', 'woocommerce-pledg'),
                'type' => 'select',
                'options' => $lang,
                'default' => 'en',
            ]
        ];
        $a_descriptions = [
            'description_lang' => [
                'title' => __('Description lang', 'woocommerce-pledg'),
                'type' => 'select',
                'options' => $lang,
                'default' => 'en',
            ]
        ];
        foreach ($lang as $value) {
            $a_titles = array_merge(
                $a_titles,
                [
                    ('title_' . $value) => [
                        'title' => __('Title', 'woocommerce-pledg') . ' (' . $value . ')',
                        'type' => 'text',
                        'default' => '',
                    ]
                ]
            );
            $a_descriptions = array_merge(
                $a_descriptions,
                [
                    ('description_' . $value) => [
                        'title' => __('Description', 'woocommerce-pledg') . ' (' . $value . ')',
                        'type' => 'text',
                        'default' => '',
                    ]
                ]
            );
        }

        $this->form_fields = array_merge(
            [
                'enabled' => [
                    'title' => __('Activate/Deactivate', 'woocommerce-pledg'),
                    'label' => sprintf(__('Activate %s', 'woocommerce-pledg'), PLEDG_OPERATOR),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ],
                'mode' => [
                    'title' => __('Sandbox mode/Production Mode', 'woocommerce-pledg'),
                    'label' => __('Production Mode', 'woocommerce-pledg'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ],
                'merchant_id' => [
                    'title' => __('Merchant ID', 'woocommerce-pledg'),
                    'type' => 'text',
                    'default' => '',
                    'custom_attributes' => [
                        'data-countries' => json_encode($woocommerce->countries->get_allowed_countries())
                    ]
                ],
                'secret_key' => [
                    'title' => __('Secret Key', 'woocommerce-pledg'),
                    'type' => 'text',
                    'default' => '',
                ]
            ],
            $a_titles,
            $a_descriptions,
            [
                'minAmount' => [
                    'title' => __('Order minimum amount', 'woocommerce-pledg'),
                    'type' => 'number',
                    'desc' => true,
                    'desc_tip' => __('Minimum transaction amount, zero does not define a minimum', 'woocommerce-pledg'),
                    'default' => 0
                ],
                'maxAmount' => [
                    'title' => __('Order maximum amount', 'woocommerce-pledg'),
                    'type' => 'number',
                    'desc' => true,
                    'desc_tip' => __('Maximum transaction amount, zero does not define a maximum', 'woocommerce-pledg'),
                    'default' => 0
                ],
                'logo' => [
                    'title' => __('Logo', 'woocommerce-pledg'),
                    'type' => 'text',
                    'desc' => true,
                    'desc_tip' => __('Logo to show next to payment method. Click on the input box to add an image or keep blank for default image.', 'woocommerce-pledg'),
                    'default' => ''
                ]
            ]
        );
    }

    /**
     * Function called once button "Place order" has been called
     * Redirecting to Pledg front
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        return [
            'result' => 'success',
            'redirect' => $this->get_request_url($order)
        ];
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        global $woocommerce;
        $merchantId = $this->get_option('merchant_id');

        if ($merchantIdDecoded = json_decode($merchantId, true)) {
            if ($woocommerce->customer && array_key_exists($woocommerce->customer->get_billing_country(), $merchantIdDecoded)) {
                $merchantId = $merchantIdDecoded[$woocommerce->customer->get_billing_country()];
            } elseif (array_key_exists('default', $merchantIdDecoded)) {
                $merchantId = $merchantIdDecoded['default'];
            } elseif (array_key_exists('FR', $merchantIdDecoded)) {
                $merchantId = $merchantIdDecoded['FR'];
            } else {
                $merchantId = reset($merchantIdDecoded);
            }
        }

        return $merchantId;
    }

    /**
     * Function to manage the creation of the url to Pledg Front
     * redirectUrl : URL redirected by Pledg once payment has succeeded
     * cancelUrl : URL redirected by Pledg when payment has been canceled
     * paymentNotificationUrl : Webhook used by Pledg to update the payment status
     */
    public function get_request_url(WC_Order $order)
    {
        $endpoint = ($this->isInProductionMode()) ?
            WC_Pledg_Constants::PLEDG_PROD_FRONT_URI . '/purchase?' :
            WC_Pledg_Constants::PLEDG_STAGING_FRONT_URI . '/purchase?'
        ;

        $items = $order->get_items();
        $id = $order->get_id();
        $title = [];
        foreach ($items as $item) {
            array_push($title, stripslashes($item->get_name()));
        }

        $ref = PLEDG_OPERATOR . "_" . $id . "_" . time();
        $args =
            [
                'merchantUid' => $this->getMerchantId(),
                'amountCents' => intval($order->get_total() * 100),
                'title' => addslashes(($title) ? implode(', ', $title) : ''),
                'subtitle' => addslashes(get_bloginfo('name')),
                'currency' => get_woocommerce_currency(),
                'lang' => get_locale(),
                'showCloseButton' => true,
                'countryCode' => $order->get_billing_country(),
                'metadata' => $this->create_metadata(),
                'email' => $order->get_billing_email(),
                'reference' => $ref,
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'phoneNumber' => $order->get_billing_phone(),
                'address' => [
                    'street' => $order->get_billing_address_1() . " - " . $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'zipcode' => $order->get_billing_postcode(),
                    'stateProvince' => "",
                    'country' => $order->get_billing_country(),
                ],
                'shippingAddress' => [
                    'street' => $order->get_shipping_address_1() . " - " . $order->get_shipping_address_2(),
                    'city' => $order->get_shipping_city(),
                    'zipcode' => $order->get_shipping_postcode(),
                    'stateProvince' => "",
                    'country' => $order->get_shipping_country(),
                ],
                'redirectUrl' => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->get_return_url($order))),
                'cancelUrl' => esc_url_raw($order->get_cancel_order_url_raw(wc_get_checkout_url())),
                'paymentNotificationUrl' => esc_url_raw(WC_Webhook_REST_Controller::get_order_webhook_from_id($id)),
                'errorNotificationUrl' => esc_url_raw(WC_Webhook_REST_Controller::get_order_error_webhook_from_id($id)),
            ];

        if (empty($this->get_option('secret_key'))) {
            $args['metadata'] = json_encode($args['metadata']);
            $args['address'] = json_encode($args['address']);
            $args['shippingAddress'] = json_encode($args['shippingAddress']);
            return $endpoint . http_build_query($args, '', '&');
        } else {
            $signature = $this->JWT_sign(['data' => $args], $this->get_option('secret_key'));
            return $endpoint . $signature;
        }
    }

    /**
     * Function to JWT sign the payload.
     */
    public function JWT_sign($args, $secret)
    {
        $signature = 'signature=' . JWT::encode($args, $secret);
        return $signature;
    }

    public function is_available()
    {
        global $woocommerce;
        if ($woocommerce->cart && 0 < $this->get_order_total() && $this->min_amount > 0 && $this->get_order_total() < $this->min_amount) {
            return false;
        }
        return parent::is_available();
    }

    public function payment_fields()
    {
        echo '<input type="hidden" name="merchantUid_' . $this->id . '" value="' . $this->getMerchantId() . '"/>';
        echo '<input type="hidden" name="payment_detail_trad_' . $this->id . '" value="' . esc_html($this->payment_detail_trad(get_woocommerce_currency_symbol())) . '"/>';
        echo '<input type="hidden" name="locale_' . $this->id . '" value=\'' . str_replace("_", "-", get_locale()) . '\'/>';

        $urlApi = ['payload' => [
            'created' => date("Y-m-d"),
            'amount_cents' => round($this->get_order_total() * 100)
        ]];

        $urlApi['url'] = ($this->isInProductionMode()) ?
            WC_Pledg_Constants::PLEDG_PROD_BACK_URI . '/users/me/merchants/' :
            WC_Pledg_Constants::PLEDG_STAGING_BACK_URI . '/users/me/merchants/';
        $urlApi['url'] .= $this->getMerchantId();
        $urlApi['url'] .= "/simulate_payment_schedule";
        echo '<input type="hidden" name="url_api_' . $this->id . '" value=\'' . json_encode($urlApi) . '\'/>';
        parent::payment_fields();
    }

    public function payment_detail_trad($currency)
    {
        $ret = [
            /* translators: Has the currency sign to be before or after the amount to pay (€1 or 1€), after by default. */
            'currencySign' => __('Currency symbol ("before" or "after")', 'woocommerce-pledg'),
            'deadlineTrad' => __('Deadline', 'woocommerce-pledg'),
            'theTrad' => __('the', 'woocommerce-pledg'),
            /* translators: %s: Will be replaced by the amount of fees (including currency symbol). */
            'feesTrad' => __('(including %s of fees)', 'woocommerce-pledg'),
            /* translators: %s1: amount payed (inc. currency symbol), %s2: date of payment. */
            'deferredTrad' => __('I\'ll pay %s1 on %s2.', 'woocommerce-pledg'),
        ];
        if ($ret['currencySign'] !== "before" && $ret['currencySign'] !== "after") {
            $ret['currencySign'] = "after";
        }
        $ret['currency'] = $currency;

        return json_encode($ret);
    }

    /**
     * @return bool
     */
    public function isInProductionMode()
    {
        return ($this->get_option('mode') === 'yes');
    }

    /**
     * @return string
     */
    public function getPledgMerchantUri()
    {
        return ($this->isInProductionMode() ?
            WC_Pledg_Constants::PLEDG_PROD_BACK_URI :
            WC_Pledg_Constants::PLEDG_STAGING_BACK_URI
        ) . '/merchants/' . $this->getMerchantId();
    }
}
