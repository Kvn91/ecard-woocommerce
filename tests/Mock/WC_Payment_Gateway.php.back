<?php

class WC_Payment_Gateway
{
    protected $options = [
        'logo' => 'logo.png',
        'minAmount' => 0,
        'maxAmount' => 10000,
        'description' => 'Instalment payment, simple and accessible.',
        'title_fr' => 'Paiement 3x',
        'description_fr' => 'Payez en 3 fois sans frais',
        'title_en' => 'Payment 3x',
        'description_en' => 'Pay in 3 times without fees',
        'mode' => 'no',
        'merchant_id' => '{["FR": "mer_france"]}'
    ];

    protected function get_option(string $name)
    {
        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return '';
    }

    public function setOption(string $name, string $value)
    {
        $this->options[$name] = $value;
    }

    protected function init_settings()
    {
    }

    protected function get_return_url($order): string
    {
        return REDIRECT_URL;
    }
}
