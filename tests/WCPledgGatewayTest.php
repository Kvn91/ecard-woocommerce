<?php

use WP_Mock\Tools\TestCase;

class WC_Pledg_Gateway_Test extends TestCase
{
    private const UNSIGNED_PAYMENT_GATEWAY_ID = 1;
    private const SIGNED_PAYMENT_GATEWAY_ID = 2;
    private const CHECKOUT_URL = 'http://example.com/checkout';

    /**
     * @var WC_Pledg_Gateway
     */
    private $unsignedPaymentGateway;

    /**
     * @var WC_Pledg_Gateway
     */
    private $signedPaymentGateway;

    public function setUp(): void
    {
        \WP_Mock::setUp();

        require_once __DIR__ . '/Mock/WC_Payment_Gateway.php';
        require_once __DIR__ . '/Mock/WC_Pledg_Constants.php';
        require_once __DIR__ . '/Mock/WC_Pledg_REST_Controller.php';
        require_once __DIR__ . '/Mock/JWT.php';
        require_once __DIR__ . '/../includes/classes/WC_Pledg_Gateway.php';

        // Mocking WC_Countries class
        $wcCountries = Mockery::mock('\WC_Countries');
        $wcCountries->shouldReceive('get_allowed_countries')
            ->twice()
            ->andReturn(['FR' => 'France']);

        // Mocking Main WooCommerce class
        global $woocommerce;
        $woocommerce = Mockery::mock('\WooCommerce');
        $woocommerce->countries = $wcCountries;

        \WP_Mock::userFunction('get_locale', [
            'return' => 'fr_FR',
        ]);

        $this->unsignedPaymentGateway = new WC_Pledg_Gateway();
        $this->unsignedPaymentGateway->id = self::UNSIGNED_PAYMENT_GATEWAY_ID;

        $this->signedPaymentGateway = new WC_Pledg_Gateway();
        $this->signedPaymentGateway->id = self::SIGNED_PAYMENT_GATEWAY_ID;
        $this->signedPaymentGateway->setOption('secret_key', 'azertyuiop');
    }

    public function testPaymentGatewayIsWellConstructed()
    {
        $this->assertEquals('logo.png', $this->unsignedPaymentGateway->icon);
        $this->assertEquals(0, $this->unsignedPaymentGateway->min_amount);
        $this->assertEquals(10000, $this->unsignedPaymentGateway->max_amount);
        $this->assertEquals('Pledg', $this->unsignedPaymentGateway->method_title);
        $this->assertEquals('Instalment payment, simple and accessible.', $this->unsignedPaymentGateway->method_description);
        $this->assertEquals('Paiement 3x', $this->unsignedPaymentGateway->title);
        $this->assertEquals('Payez en 3 fois sans frais', $this->unsignedPaymentGateway->description);
    }

    public function testPaymentGatewayUpdateOptionsActionIsInitiated()
    {
        \WP_Mock::expectActionAdded(
            'woocommerce_update_options_payment_gateways_' . self::UNSIGNED_PAYMENT_GATEWAY_ID,
            [$this->unsignedPaymentGateway, 'process_admin_options']
        );
        $this->unsignedPaymentGateway->init();

        $this->assertActionsCalled();
    }

    public function testGetUnsignedRequestUrl()
    {
        $orderArr = self::getOrder();
        $order = self::getOrderMock($orderArr);

        \WP_Mock::userFunction('get_bloginfo', [
            'args' => ['name'],
            'return' => 'My shop',
        ]);
        \WP_Mock::userFunction('get_woocommerce_currency', [
            'return' => 'EUR',
        ]);
        \WP_Mock::userFunction('get_locale', [
            'return' => 'fr_FR',
        ]);
        \WP_Mock::userFunction('get_file_data', [
            'return' => ['version' => '1.0.0'],
        ]);

        // Mocking WC_Cart class
        $wcCart = Mockery::mock('\WC_Cart');
        $wcCart->shouldReceive('get_shipping_packages')
            ->once()
            ->andReturn([]);

        // Mocking Main WooCommerce class with a cart
        global $woocommerce;
        $woocommerce = Mockery::mock('\WooCommerce');
        $woocommerce->cart = $wcCart;

        $wcLogger = Mockery::mock('\WC_Logger');
        $wcLogger->shouldReceive('error')->once();
        \WP_Mock::userFunction('wc_get_logger', [
            'return' => $wcLogger
        ]);

        \WP_Mock::userFunction('add_query_arg', [
            'args' => ['utm_nooverride', 1, REDIRECT_URL],
            'return' => 'http://example.com/tank-you'
        ]);
        \WP_Mock::userFunction('wc_get_checkout_url', [
            'return' => self::CHECKOUT_URL
        ]);

        $requestUrl = $this->unsignedPaymentGateway->get_request_url($order);

        $this->assertMatchesRegularExpression(
            '/^(https:\/\/test\.staging\.front\.ecard\.pledg\.co\/purchase\?merchantUid=%7B%5B%22FR%22%3A\+%22mer_france%22%5D%7D&amountCents=15000&title=item\+1%2C\+item\+2&subtitle=My\+shop&currency=EUR&lang=fr_FR&showCloseButton=1&countryCode=FRANCE&metadata=%7B%22plugin%22%3A%22woocommerce-pledg-plugin1\.0\.0%22%7D&email=user%40example\.com&reference=Pledg_1515_)\d{10}(&firstName=John&lastName=Doe&phoneNumber=0123456789&address=%7B%22street%22%3A%2252\+RUE\+DES\+FLEURS\+-\+%22%2C%22city%22%3A%22LIBOURNE%22%2C%22zipcode%22%3A%2233500%22%2C%22stateProvince%22%3A%22%22%2C%22country%22%3A%22FRANCE%22%7D&shippingAddress=%7B%22street%22%3A%2252\+RUE\+DES\+FLEURS\+-\+%22%2C%22city%22%3A%22LIBOURNE%22%2C%22zipcode%22%3A%2233500%22%2C%22stateProvince%22%3A%22%22%2C%22country%22%3A%22FRANCE%22%7D&redirectUrl=http%3A%2F%2Fexample\.com%2Ftank-you&cancelUrl=http%3A%5C%2F%2F%5Cexample\.com%5C%2Fcheckout&paymentNotificationUrl=pledg%2Fv2%2Forder%2F1515%2F&errorNotificationUrl=pledg%2Fv2%2Forder%2F1515%2Ferror)$/',
            $requestUrl
        );
    }

    public function testGetSignedRequestUrl()
    {
        $orderArr = self::getOrder();
        $order = self::getOrderMock($orderArr);

        \WP_Mock::userFunction('get_bloginfo', [
            'args' => ['name'],
            'return' => 'My shop',
        ]);
        \WP_Mock::userFunction('get_woocommerce_currency', [
            'return' => 'EUR',
        ]);
        \WP_Mock::userFunction('get_locale', [
            'return' => 'fr_FR',
        ]);
        \WP_Mock::userFunction('get_file_data', [
            'return' => ['version' => '1.0.0'],
        ]);

        // Mocking WC_Cart class
        $wcCart = Mockery::mock('\WC_Cart');
        $wcCart->shouldReceive('get_shipping_packages')
            ->once()
            ->andReturn([]);

        // Mocking Main WooCommerce class with a cart
        global $woocommerce;
        $woocommerce = Mockery::mock('\WooCommerce');
        $woocommerce->cart = $wcCart;

        $wcLogger = Mockery::mock('\WC_Logger');
        $wcLogger->shouldReceive('error')->once();
        \WP_Mock::userFunction('wc_get_logger', [
            'return' => $wcLogger
        ]);

        \WP_Mock::userFunction('add_query_arg', [
            'args' => ['utm_nooverride', 1, REDIRECT_URL],
            'return' => 'http://example.com/tank-you'
        ]);
        \WP_Mock::userFunction('wc_get_checkout_url', [
            'return' => self::CHECKOUT_URL
        ]);

        $requestUrl = $this->signedPaymentGateway->get_request_url($order);

        $this->assertMatchesRegularExpression(
            '/^(https:\/\/test\.staging\.front\.ecard\.pledg\.c\o\/purchase\?signature=jwt_encoded)$/',
            $requestUrl
        );
    }

    public function testGetMerchantId()
    {
        global $woocommerce;
        $woocommerce = Mockery::mock('\WooCommerce');
        $customer = Mockery::mock('\WC_Customer');
        $customer->shouldReceive('get_billing_country')
            ->andReturn('DA')
        ;
        $woocommerce->customer = $customer;

        // Test with customer merchant
        $this->unsignedPaymentGateway->setOption('merchant_id', json_encode([
            'NR' => 'mer_norway',
            'FR' => 'mer_france',
            'DA' => 'mer_danmark',
            'default' => 'mer_default',
            'IT' => 'mer_italy',
        ]));
        $merchantId = $this->unsignedPaymentGateway->getMerchantId();

        $this->assertEquals('mer_danmark', $merchantId);

        // Test with default merchant
        $this->unsignedPaymentGateway->setOption('merchant_id', json_encode([
            'NR' => 'mer_norway',
            'FR' => 'mer_france',
            'default' => 'mer_default',
            'IT' => 'mer_italy',
        ]));
        $merchantId = $this->unsignedPaymentGateway->getMerchantId();

        $this->assertEquals('mer_default', $merchantId);

        // Test with FR merchant
        $this->unsignedPaymentGateway->setOption('merchant_id', json_encode([
            'NR' => 'mer_norway',
            'FR' => 'mer_france',
            'IT' => 'mer_italy',
        ]));
        $merchantId = $this->unsignedPaymentGateway->getMerchantId();

        $this->assertEquals('mer_france', $merchantId);

        // Test with first merchant
        $this->unsignedPaymentGateway->setOption('merchant_id', json_encode([
            'NR' => 'mer_norway',
            'IT' => 'mer_italy',
        ]));
        $merchantId = $this->unsignedPaymentGateway->getMerchantId();

        $this->assertEquals('mer_norway', $merchantId);
    }

    private static function getOrderMock(array $orderArr)
    {
        $item1 = Mockery::mock('\WC_Order_Item');
        $item1->shouldReceive('get_name')
            ->once()
            ->andReturn('item 1')
        ;

        $item2 = Mockery::mock('\WC_Order_Item');
        $item2->shouldReceive('get_name')
            ->once()
            ->andReturn('item 2')
        ;

        $order = Mockery::mock('\WC_Order');
        $order->shouldReceive('get_items')
            ->once()
            ->andReturn([$item1, $item2])
        ;
        $order->shouldReceive('get_id')
            ->once()
            ->andReturn($orderArr['id'])
        ;
        $order->shouldReceive('get_total')
            ->once()
            ->andReturn($orderArr['total'])
        ;
        $order->shouldReceive('get_billing_country')
            ->twice()
            ->andReturn($orderArr['billing_country'])
        ;
        $order->shouldReceive('get_billing_email')
            ->once()
            ->andReturn($orderArr['billing_email'])
        ;
        $order->shouldReceive('get_billing_first_name')
            ->once()
            ->andReturn($orderArr['billing_first_name'])
        ;
        $order->shouldReceive('get_billing_last_name')
            ->once()
            ->andReturn($orderArr['billing_last_name'])
        ;
        $order->shouldReceive('get_billing_phone')
            ->once()
            ->andReturn($orderArr['billing_phone'])
        ;
        $order->shouldReceive('get_billing_address_1')
            ->once()
            ->andReturn($orderArr['billing_address_1'])
        ;
        $order->shouldReceive('get_billing_address_2')
            ->once()
            ->andReturn($orderArr['billing_address_2'])
        ;
        $order->shouldReceive('get_billing_city')
            ->once()
            ->andReturn($orderArr['billing_city'])
        ;
        $order->shouldReceive('get_billing_postcode')
            ->once()
            ->andReturn($orderArr['billing_postcode'])
        ;
        $order->shouldReceive('get_shipping_address_1')
            ->once()
            ->andReturn($orderArr['shipping_address_1'])
        ;
        $order->shouldReceive('get_shipping_address_2')
            ->once()
            ->andReturn($orderArr['shipping_address_2'])
        ;
        $order->shouldReceive('get_shipping_city')
            ->once()
            ->andReturn($orderArr['shipping_city'])
        ;
        $order->shouldReceive('get_shipping_postcode')
            ->once()
            ->andReturn($orderArr['shipping_postcode'])
        ;
        $order->shouldReceive('get_shipping_country')
            ->once()
            ->andReturn($orderArr['shipping_country'])
        ;
        $order->shouldReceive('get_cancel_order_url_raw')
            ->once()
            ->with(self::CHECKOUT_URL)
            ->andReturn('http:\//\example.com\/checkout')
        ;

        return $order;
    }

    private static function getOrder(): array
    {
        return [
            'id' => 1515,
            'total' => 150,
            'billing_country' => 'FRANCE',
            'billing_email' => 'user@example.com',
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_phone' => '0123456789',
            'billing_address_1' => '52 RUE DES FLEURS',
            'billing_address_2' => '',
            'billing_city' => 'LIBOURNE',
            'billing_postcode' => '33500',
            'shipping_address_1' => '52 RUE DES FLEURS',
            'shipping_address_2' => '',
            'shipping_city' => 'LIBOURNE',
            'shipping_postcode' => '33500',
            'shipping_country' => 'FRANCE',
        ];
    }

    private static function getFRCustomerMock()
    {
        return $customer;
    }

    public function tearDown(): void
    {
        \WP_Mock::tearDown();
    }
}
