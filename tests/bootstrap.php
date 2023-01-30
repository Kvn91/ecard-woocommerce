<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Pledg
 */

const WOOCOMMERCE_PLEDG_PLUGIN_DIR = '/home/user/var/www/wordpress/wp-content/plugins/my-plugin/';
const WOOCOMMERCE_PLEDG_PLUGIN_DIR_URL = 'http://example.com/wp-content/plugins/my-plugin/';
const PLEDG_OPERATOR = 'Pledg';
const PLEDG_OPERATOR_LOWER = 'pledg';

const REDIRECT_URL = 'http://example.com/thank-you';

// First we need to load the composer autoloader so we can use WP Mock
require_once __DIR__ . '/../vendor/autoload.php';

// Now call the bootstrap method of WP Mock
WP_Mock::bootstrap();
