<?php

/**
 * Pledg Admin Settings
 *
 * Render settings for the Pledg plugin
 */
class WC_Pledg_Admin_Settings
{
    /**
     * Form options
     *
     * @var array
     */
    private $options;

    private const SETTINGS_FIELDS = [
        'product_widget' => '1',
        'cart_widget' => '1',
        'payment_pre_selected' => '1',
    ];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'pledg_add_settings_page']);
        add_action('admin_init', [$this, 'pledg_register_settings']);
    }

    public function pledg_render_options()
    {
        $this->options = get_option('pledg_plugin_options');
        if (!$this->options) { // Set default options
            $this->options = self::SETTINGS_FIELDS;
        } ?>
        <div class="wrap">
            <h1><?php echo sprintf(__('%s parameters', 'woocommerce-pledg'), PLEDG_OPERATOR); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('pledg_plugin_options_group');
        do_settings_sections(PLEDG_OPERATOR_LOWER . '-admin-settings');
        submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function pledg_add_settings_page()
    {
        add_options_page(
            sprintf(__('%s parameters', 'woocommerce-pledg'), PLEDG_OPERATOR),
            PLEDG_OPERATOR,
            'manage_options',
            PLEDG_OPERATOR_LOWER . '-admin-settings',
            [$this, 'pledg_render_options']
        );
    }

    public function pledg_register_settings()
    {
        register_setting(
            'pledg_plugin_options_group',
            'pledg_plugin_options'
        );

        add_settings_section(
            'pledg_widget_settings',
            __('Widget parameters', 'woocommerce-pledg'),
            [$this, 'pledg_settings_info'],
            PLEDG_OPERATOR_LOWER . '-admin-settings'
        );

        add_settings_field(
            'product_widget',
            __('Enable product widget', 'woocommerce-pledg'),
            [$this, 'product_widget_callback'],
            PLEDG_OPERATOR_LOWER . '-admin-settings',
            'pledg_widget_settings'
        );

        add_settings_field(
            'cart_widget',
            __('Enable cart widget', 'woocommerce-pledg'),
            [$this, 'cart_widget_callback'],
            PLEDG_OPERATOR_LOWER . '-admin-settings',
            'pledg_widget_settings'
        );

        add_settings_field(
            'payment_pre_selected',
            sprintf(__(
                'Enable pre-selection of %s payment method if the widget has been clicked',
                'woocommerce-pledg'
            ), PLEDG_OPERATOR),
            [$this, 'pre_selection_callback'],
            PLEDG_OPERATOR_LOWER . '-admin-settings',
            'pledg_widget_settings'
        );
    }

    public function pledg_settings_info()
    {
        echo '<p>' . __('You can set the widget option here', 'woocommerce-pledg') . '</p>';
    }

    public function product_widget_callback()
    {
        $checked = isset($this->options['product_widget']) ?
            checked(1, $this->options['product_widget'], false) : '';

        echo'<input id="pledg_setting_product_widget" name="pledg_plugin_options[product_widget]" type="checkbox" value="1"'
            . $checked . '/>';
    }

    public function cart_widget_callback()
    {
        $checked = isset($this->options['cart_widget']) ?
            checked(1, $this->options['cart_widget'], false) : '';
        echo'<input id="pledg_setting_cart_widget" name="pledg_plugin_options[cart_widget]" type="checkbox" value="1"'
            . $checked . '/>';
    }

    public function pre_selection_callback()
    {
        $checked = isset($this->options['payment_pre_selected']) ?
            checked(1, $this->options['payment_pre_selected'], false) : '';

        echo'<input id="pledg_setting_payment_pre_selected" name="pledg_plugin_options[payment_pre_selected]" type="checkbox" value="1"'
            . $checked . '/>';
    }
}
