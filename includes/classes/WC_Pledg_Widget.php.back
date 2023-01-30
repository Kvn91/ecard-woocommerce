<?php

defined('ABSPATH') || exit;

/**
 * Pledg Widget
 *
 * If the merchant has activated the options, render the Pledg widget on the product page
 * and/or on the cart page
 */
class WC_Pledg_Widget
{
    /**
     * @var bool
     */
    private $hasDeferredFees = false;

    /**
     * @var bool
     */
    private $hasInstallmentFees = false;

    /**
     * @var array
     */
    private $pledgGateways;

    public function __construct()
    {
        $pledgSettings = get_option('pledg_plugin_options');
        $this->pledgGateways = $this->getPledgGateways();

        if (!empty($this->pledgGateways)) {
            if (!$pledgSettings ||
                (array_key_exists('product_widget', $pledgSettings) && $pledgSettings['product_widget'])
            ) {
                add_action('woocommerce_single_product_summary', [$this, 'renderProductWidget']);
            }
            if (!$pledgSettings ||
                (array_key_exists('cart_widget', $pledgSettings) && $pledgSettings['cart_widget'])
            ) {
                add_action('woocommerce_before_cart_totals', [$this, 'renderCartWidget']);
            }
            if (!$pledgSettings ||
                (array_key_exists('payment_pre_selected', $pledgSettings) && $pledgSettings['payment_pre_selected'])
            ) {
                add_action('wp_footer', [$this, 'add_checkout_pre_selection_js']);
            }

            add_action('wp_enqueue_scripts', [$this, 'widgetScripts']);
            add_action('wp_footer', [$this, 'pledg_modal_wrapper']);

            add_action('wp_footer', [$this, 'pledg_modal_installment_content']);
            add_action('wp_footer', [$this, 'pledg_modal_deferred_content']);
        }
    }

    /**
     * @return void
     */
    public function widgetScripts()
    {
        wp_register_script('pledg_widget', WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'assets/js/pledg_widget.js', ['jquery'], false, true);
        wp_enqueue_script('pledg_widget');
        wp_localize_script('pledg_widget', 'pledg_payment_types', WC_Pledg_Constants::PLEDG_PAYMENT_TYPES);

        wp_enqueue_style('pledg_widget', WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'assets/css/pledg_widget.css');
    }

    /**
     * @return void
     */
    public function renderProductWidget()
    {
        echo '<div id="pledg-widget">';
        if ('Django' === 'Pledg') {
            echo '<div id="pledg-widget-mention-logo"><img class="django-widget-logo" heigth="20" width="20" src="'
                . WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'django.png"></div>';
        }
        echo '<div id="pledg-widget-mention">';

        if (array_key_exists(WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment'], $this->pledgGateways)) {
            $this->installmentHtml($this->pledgGateways[WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment']], 'product');
        }

        if (array_key_exists(WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred'], $this->pledgGateways)) {
            $this->deferredHtml($this->pledgGateways[WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred']], 'product');
        }

        echo '</div></div>';
    }

    /**
     * @return void
     */
    public function renderCartWidget()
    {
        echo '<div id="pledg-widget">';
        if ('Django' === 'Pledg') {
            echo '<div id="pledg-widget-mention-logo"><img class="django-widget-logo" heigth="20" width="20" src="'
                . WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'django.png"></div>';
        }
        echo '<div id="pledg-widget-mention">';

        if (array_key_exists(WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment'], $this->pledgGateways)) {
            $this->installmentHtml($this->pledgGateways[WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment']], 'cart');
        }

        if (array_key_exists(WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred'], $this->pledgGateways)) {
            $this->deferredHtml($this->pledgGateways[WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred']], 'cart');
        }

        echo '</div></div>';
    }

    /**
     * @param array $paymentMethods
     * @param string $widgetType
     * @return void
     */
    private function installmentHtml(array $intallmentGateways, string $widgetType)
    {
        sort($intallmentGateways);
        $fees = !$this->hasInstallmentFees ? __(', free of charge', 'woocommerce-pledg') : '';

        echo '<div>' . sprintf(
            __('Pay in installments (%s installments)%s ', 'woocommerce-pledg'),
            implode(', ', $intallmentGateways),
            $fees
        );
        echo '<span class="pledg-modal-link dashicons dashicons-editor-help"
                  data-payment-type="' . WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment'] . '"
                  data-widget-type"' . $widgetType . '"></span></div>';
    }

    /**
     * @param array $paymentMethods
     * @param string $widgetType
     * @return void
     */
    private function deferredHtml(array $deferredGateways, string $widgetType)
    {
        sort($deferredGateways);
        $fees = !$this->hasDeferredFees ? __(', free of charge', 'woocommerce-pledg') : '';

        echo '<div>' . sprintf(
            __('Pay later (in %s days)%s ', 'woocommerce-pledg'),
            implode(', ', $deferredGateways),
            $fees
        );
        echo '<span class="pledg-modal-link dashicons dashicons-editor-help"
                  data-payment-type="' . WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred'] . '"
                  data-widget-type="' . $widgetType . '"></span></div>';
    }

    /**
     * @return array
     */
    private function getPledgGateways()
    {
        $pledgGateways = [];
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $logger = wc_get_logger();

        foreach ($gateways as $gateway) {
            if ($gateway instanceof WC_Pledg_Gateway && $gateway->enabled) {
                $merchantUri = $gateway->getPledgMerchantUri();
                $response = json_decode(wp_remote_retrieve_body(wp_remote_get($merchantUri)));

                if (!$response) {
                    continue;
                } elseif (is_wp_error($response)) {
                    $logger->error(PLEDG_OPERATOR_LOWER . ' Error while trying to get merchant : ' . $response->get_error_message());
                    continue;
                } elseif (\property_exists($response, 'error')) {
                    $logger->error(PLEDG_OPERATOR_LOWER . ' Error while trying to get merchant : ' . $response->error->debug);
                    continue;
                }

                if (\property_exists($response, 'payment_type')
                    && strtolower($response->payment_type) === WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred']
                ) {
                    $pledgGateways[WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred']][]
                        = $response->delay_in_days;

                    if (!$this->hasDeferredFees && ( // Has buyer fees
                        count($response->buyer_fees_percents) > 1 // Has several tresholds
                        || $response->buyer_fees_percents[0]->buyer_fees_percent != 0 // Has fees on first treshold
                    )) {
                        $this->hasDeferredFees = true;
                    }
                } elseif (\property_exists($response, 'payment_type')
                    && strtolower($response->payment_type) === WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment']
                ) {
                    $pledgGateways[WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment']][]
                        = $response->installments_nb;

                    if (!$this->hasInstallmentFees && ( // Has buyer fees
                        count($response->buyer_fees_percents) > 1 // Has several tresholds
                        || $response->buyer_fees_percents[0]->buyer_fees_percent != 0 // Has fees on first treshold
                    )) {
                        $this->hasInstallmentFees = true;
                    }
                }
            }
        }

        return $pledgGateways;
    }

    /**
     * @return void
     */
    public function pledg_modal_wrapper()
    {
        ?>
        <div id="pledg-popup" class="pledg-popup-overlay">
            <div class="pledg-popup"></div>
        </div>
        <?php
    }

    /**
     * @return void
     */
    public function pledg_modal_installment_content()
    {
        ?>
        <div id="pledg-popup-installment">
            <?php if ('Django' === 'Pledg') {
                echo '<img class="django-widget-logo" heigth="42" width="95" src="'
                    . WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'django-large.png">';
            } ?>
            <span class="pledg-popup-close">&times;</span>
            <div class="pledg-popup-header">
                <p><?php echo __('Buy now.', 'woocommerce-pledg'); ?></p>
                <p>
                    <?php echo __('Pay in installments with Pledg', 'woocommerce-pledg'); ?>
                    <?php if (!$this->hasInstallmentFees) {
                        echo __('Free of charge.', 'woocommerce-pledg');
                    } ?>
                </p>
            </div>
            <div class="pledg-popup-content">
                <div class="pledg-popup-howto">
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">1</div>
                        <span><?php echo __('Validate your basket', 'woocommerce-pledg'); ?></span>
                    </div>
                    <div class="pledg-popup-between-step"></div>
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">2</div>
                        <span><?php echo __('Select the payment in installment', 'woocommerce-pledg'); ?></span>
                    </div>
                    <div class="pledg-popup-between-step"></div>
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">3</div>
                        <span><?php echo __('Fill in your card number', 'woocommerce-pledg'); ?></span>
                    </div>
                    <div class="pledg-popup-between-step"></div>
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">4</div>
                        <span>
                            <?php echo __('The first share is debited today. The following shares will be automatically debited in the following months', 'woocommerce-pledg'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="pledg-popup-footer">
                <p>
                    <?php echo __('*Loan subject to conditions. Pledg', 'woocommerce-pledg'); ?>
                    <?php if ($this->hasInstallmentFees) {
                        echo __('Fees may apply.', 'woocommerce-pledg');
                    } ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * @return void
     */
    public function pledg_modal_deferred_content()
    {
        ?>
        <div id="pledg-popup-deferred">
            <?php if ('Django' === 'Pledg') {
                echo '<img class="django-widget-logo" heigth="42" width="95" src="'
                    . WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL . 'django-large.png">';
            } ?>
            <span class="pledg-popup-close">&times;</span>
            <div class="pledg-popup-header">
                <p><?php echo __('Buy now.', 'woocommerce-pledg'); ?></p>
                <p>
                    <?php echo __('Pay later with Pledg', 'woocommerce-pledg'); ?>
                    <?php if (!$this->hasDeferredFees) {
                        echo __('Free of charge.', 'woocommerce-pledg');
                    } ?>
                </p>
            </div>
            <div class="pledg-popup-content">
                <div class="pledg-popup-howto">
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">1</div>
                        <span><?php echo __('Validate your basket', 'woocommerce-pledg'); ?></span>
                    </div>
                    <div class="pledg-popup-between-step"></div>
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">2</div>
                        <span><?php echo __('Select the deferred payment', 'woocommerce-pledg'); ?></span>
                    </div>
                    <div class="pledg-popup-between-step"></div>
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">3</div>
                        <span><?php echo __('Fill in your card number', 'woocommerce-pledg'); ?></span>
                    </div>
                    <div class="pledg-popup-between-step"></div>
                    <div class="pledg-popup-step">
                        <div class="pledg-popup-bullet">4</div>
                        <span>
                            <?php echo __('The payment will be debited later, depending on the deadline you have chosen', 'woocommerce-pledg'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="pledg-popup-footer">
                <p>
                    <?php echo __('*Loan subject to conditions. Pledg', 'woocommerce-pledg'); ?>
                    <?php if ($this->hasDeferredFees) {
                        echo __('Fees may apply.', 'woocommerce-pledg');
                    } ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * @return void
     */
    public function add_checkout_pre_selection_js()
    {
        global $wp;

        if (is_checkout() && empty($wp->query_vars['order-pay']) && !isset($wp->query_vars['order-received'])) {
            ?>
            <script type="application/javascript">
              jQuery(document).ready(function ($) {
                let widgetClicked = getPledgCookie('pledg_widget');
                if (widgetClicked !== null) {
                  const regex = /pledg[1-9]{0,1}/;
                  const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
                  for (let i = 0; i < paymentMethods.length; i++) {
                    if (paymentMethods[i].value.match(regex)) {
                      $(paymentMethods[i]).click();
                      break;
                    }
                  }
                }
              });
            </script>
            <?php
        }
    }

    /**
     * @param string $paymentType
     * @return string
     */
    private static function getCguUri(string $paymentType)
    {
        if ($paymentType === WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['installment']) {
            return sprintf(WC_Pledg_Constants::PLEDG_CGU_INSTALLMENT_URI, self::getPledgLocale() ? self::getPledgLocale() . '/' : '');
        } elseif ($paymentType === WC_Pledg_Constants::PLEDG_PAYMENT_TYPES['deferred']) {
            return sprintf(WC_Pledg_Constants::PLEDG_CGU_DEFERRED_URI, self::getPledgLocale() ? self::getPledgLocale() . '/' : '');
        }
    }

    /**
     * @return string|boolean
     */
    private static function getPledgLocale()
    {
        $locale = get_locale();

        if ($locale === 'fr_FR') {
            return false;
        } else {
            return substr($locale, 0, 2);
        }
    }
}
