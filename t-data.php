<?php
/*
 * Plugin Name: T-Data Payment Gateway
 * Plugin URI: https://www.t-data.it/plugin-woocommerce-t-data-payment-gateway
 * Description: Take credit card payments on your store.
 * Author: T-Data S.r.l.
 * Author URI: https://www.t-data.it
 * Version: 1.1
 *
*/

/*
 * Include functions to add custom fields on checkout page
*/ 
require plugin_dir_path( __FILE__ ) . 'functions/tdata-functions.php';
 
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
*/
add_filter('woocommerce_payment_gateways', 'tdata_add_gateway_class');
function tdata_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Tdata_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
*/
add_action('plugins_loaded', 'tdata_init_gateway_class');
function tdata_init_gateway_class()
{

    class WC_Tdata_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor
         */
        public function __construct()
        {

            $this->id = 'tdata'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'T-Data Gateway';
            $this->method_description = 'T-Data gateway for credit card payment'; // will be displayed on the options page
            $this->supports = array(
                'products'
            );
			
            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('tdata_title');
            $this->description = $this->get_option('tdata_description');
            $this->testmode = 'yes' === $this->get_option('tdata_testmode');
            if($this->testmode) {
            	$this->token = $this->get_option('tdata_test_token');
            	$this->endpoint = $this->get_option('tdata_test_endpoint');
            } else {
            	$this->token = $this->get_option('tdata_production_token');
            	$this->endpoint = $this->get_option('tdata_production_endpoint');
            }
            $this->successpage = $this->get_option('tdata_response_merchant_url');
            $this->errorpage = $this->get_option('tdata_recovery_url');
			
            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array(
                $this,
                'payment_scripts'
            ));

            // You can also register a webhook here
            add_action('woocommerce_api_tdata-payment-completed', array(
                $this,
                'webhook'
            ));

        }

        /**
         * Plugin options
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'tdata_title' => array(
                    'title' => __('Title', 'tdata'),
                    'type' => 'text',
                    'description' => __('Text to show to the user in checkout section', 'tdata'),
                    'default' => __('Online payment', 'tdata'),
                    'desc_tip' => true,
                ),
                'tdata_description' => array(
                    'title' => __('Description', 'tdata'),
                    'type' => 'textarea',
                    'description' => __('Description to show to the user in checkout section', 'tdata'),
                    'default' => __('Pay with Paypal or Credit Card', 'tdata'),
                    'desc_tip' => true,
                ),
                'tdata_testmode' => array(
                    'title' => __('Test Mode', 'tdata'),
                    'label' => __('Enable Test Mode', 'tdata'),
                    'type' => 'checkbox',
                    'description' => __('Check to run tests using Test Tokens and Endpoints. Uncheck once the t-data Token and Production Endpoint have been configured', 'tdata'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'tdata_test_token' => array(
                    'title' => __('Token test', 'tdata'),
                    'type' => 'text',
                    'description' => __('Code to be used in the test environment. If T-Data has not yet provided you with one of your own, you can try the plugin with a public token (see the "installation" section in the marketplace)', 'tdata'),
                    'desc_tip' => true,
                    'default' => '1rxUNmQFgOTh3QJrURxsq8sDan6Q55dw'
                ),
                'tdata_test_endpoint' => array(
                    'title' => __('Endpoint test', 'tdata'),
                    'type' => 'text',
                    'description' => __('URL to use in the test environment. If T-Data has not yet provided you with one of your own, you can try the plugin with a public Endpoint (see the "installation" section in the marketplace)', 'tdata'),
                    'desc_tip' => true,
                    'default' => 'https://tdataespservices-integration.azurewebsites.net/ic/V1/GeneratePaymentToken'
                ),
                'tdata_production_token' => array(
                    'title' => __('Production Token', 'tdata'),
                    'type' => 'text',
                    'description' => __('Code to be used in the production environment. T-Data provides this data after subscribing to the service', 'tdata'),
                    'desc_tip' => true,
                ),
                'tdata_production_endpoint' => array(
                    'title' => __('Production Endpoint', 'tdata'),
                    'type' => 'text',
                    'description' => __('URL to be used in the production environment. T-Data provides this data after subscribing to the service', 'tdata'),
                    'desc_tip' => true,
                ),
                'tdata_recovery_url' => array(
                    'title' => __('RecoveryUrl', 'tdata'),
                    'type' => 'text',
                    'description' => __('SHOP URL to which T-data will redirect the customer in the event of any failure, not already managed within the T-data Gateway', 'tdata'),
                    'desc_tip' => true,
                ),
                'tdata_response_merchant_url' => array(
                    'title' => __('ResponseToMerchantUrl', 'tdata'),
                    'type' => 'text',
                    'description' => __('SHOP URL to which T-data will redirect the user in case of success of the transaction', 'tdata'),
                    'desc_tip' => true,
                ),
            );
        }

        /**
         * You will need it if you want your custom credit card form
         */
        public function payment_fields()
        {

            // ok, let's display some description before the payment form
            if ($this->description)
            {
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
        */
        public function payment_scripts()
        {
        }

        /*
         * Fields validation
        */
        public function validate_fields()
        {
        }

        /*
         * We're processing the payments here
        */
        public function process_payment($order_id)
        {

            $site_url = get_site_url();
            if($this->errorpage != '') {
            	$site_url = $this->errorpage;
            }
            
            global $woocommerce;
            $order = wc_get_order($order_id);
            $full_description = '';
            $short_description = '';
            $total = 0;
            $sdi = '0000000';
            $billing = '';
            $shipping = '';

            $piva = get_post_meta($order->get_order_number() , '_billing_piva', true);
            $pec = get_post_meta($order->get_order_number() , '_billing_pec', true);
            $sdi = get_post_meta($order->get_order_number() , '_billing_sdi', true);
            $cf = get_post_meta($order->get_order_number() , '_billing_cf', true);

            $endpoint = $this->endpoint;
			
            $success_page = site_url('/wc-api/tdata-payment-completed');

            foreach ($order->get_items() as $item_id => $item)
            {

                $product = $item->get_product();

                // Product taxes
                $tax = new WC_Tax();
                $taxes = $tax->get_rates($product->get_tax_class());
                $rates = array_shift($taxes);
                $item_rate = round(array_shift($rates));

                // Price
                $item_total_price = $item->get_total();

                // Price with VAT
                //$price = $product->get_price();
                $price = $item_total_price / $item->get_quantity();
                $price = $price + round($price * ($item_rate / 100) , 2);
                $price = round($price, 2);

                // Calculate total
                $total += ($price * $item->get_quantity());

                // Description
                $title = substr(strip_tags($product->get_title()) , 0, 95);
                $full_description = substr(strip_tags($product->get_description()) , 0, 95);
                $short_description = substr(strip_tags($product->get_short_description()) , 0, 95);

                $tmp = array();
                $tmp['SKU'] = $product->get_sku();
                $tmp['Description'] = $title;
                $tmp['Price'] = $price;
                $tmp['VATRates'] = array(
                    $item_rate
                );
                $tmp['Quantity'] = $item->get_quantity();
                $tmp['CustomsCode'] = '';
                $tmp['Discount'] = 0;
                $products[] = $tmp;
            }

            $order_data = $order->get_data();

            $shipping_cost = sanitize_text_field($order_data['shipping_total']);

            if ($shipping_cost != 0)
            {
            	$shipping_tax = sanitize_text_field($order_data['shipping_tax']);
            	$shipping_total = sanitize_text_field($order_data['shipping_total']);
                $shipping_vat_rate = $shipping_tax / $shipping_total * 100;
                $shipping_vat_rate = ceil($shipping_vat_rate);
                $shipping_cost = $shipping_cost + round($shipping_cost * ($shipping_vat_rate / 100) , 2);
            }
            else
            {
                $shipping_vat_rate = 22;
            }

            $total += $shipping_cost;

            if ($order_data['billing']['company'] == '')
            {
                $business_billing_name = sanitize_text_field($order_data['billing']['first_name']) . ' ' . sanitize_text_field($order_data['billing']['last_name']);
            }
            else
            {
                $business_billing_name = sanitize_text_field($order_data['billing']['company']);
            }

            if ($cf != '' || ($piva != '' && ($pec != '' || $sdi != '')))
            {
                $billing = array(
                    "endpoint" => sanitize_text_field($endpoint),
                    "BusinessName" => sanitize_text_field($business_billing_name),
                    "Name" => sanitize_text_field($order_data['billing']['first_name']),
                    "Surname" => sanitize_text_field($order_data['billing']['last_name']),
                    "Address" => sanitize_text_field($order_data['billing']['address_1']),
                    "City" => sanitize_text_field($order_data['billing']['city']),
                    "Zip" => sanitize_text_field($order_data['billing']['postcode']),
                    "Province" => sanitize_text_field($order_data['billing']['state']),
                    "CountryCode" => sanitize_text_field($order_data['billing']['country']),
                    "CF" => sanitize_text_field($cf),
                    "VATNumber" => sanitize_text_field($piva),
                    "Email" => sanitize_email($order_data['billing']['email']),
                    "SDICode" => sanitize_text_field($sdi),
                    "PecEmail" => sanitize_email($pec)
                );

                if ($cf == '')
                {
                    unset($billing['CF']);
                }
                if ($piva == '')
                {
                    unset($billing['VATNumber']);
                }
                if ($sdi == '')
                {
                    unset($billing['SDICode']);
                }
                if ($pec == '')
                {
                    unset($billing['PecEmail']);
                }
            }

            if ($order_data['shipping']['address_1'] != '')
            {

                if ($order_data['shipping']['company'] == '')
                {
                    $business_shipping_name = sanitize_text_field($order_data['shipping']['first_name']) . ' ' . sanitize_text_field($order_data['shipping']['last_name']);
                }
                else
                {
                    $business_shipping_name = sanitize_text_field($order_data['shipping']['company']);
                }

                $shipping = array(
                    "BusinessName" => sanitize_text_field($business_shipping_name),
                    "Address" => sanitize_text_field($order_data['shipping']['address_1']),
                    "City" => sanitize_text_field($order_data['shipping']['city']),
                    "Zip" => sanitize_text_field($order_data['shipping']['postcode']),
                    "Province" => sanitize_text_field($order_data['shipping']['state']),
                    "CountryCode" => sanitize_text_field($order_data['shipping']['country']),
                    "Reference" => sanitize_text_field($order_data['billing']['phone']),
                    "Email" => sanitize_email($order_data['billing']['email']),
                );
                
                $order_country_code = sanitize_text_field($order_data['shipping']['country']);
            }
            else
            {
                $shipping = array(
                    "BusinessName" => sanitize_text_field($business_billing_name),
                    "Address" => sanitize_text_field($order_data['billing']['address_1']),
                    "City" => sanitize_text_field($order_data['billing']['city']),
                    "Zip" => sanitize_text_field($order_data['billing']['postcode']),
                    "Province" => sanitize_text_field($order_data['billing']['state']),
                    "CountryCode" => sanitize_text_field($order_data['billing']['country']),
                    "Reference" => sanitize_text_field($order_data['billing']['phone']),
                    "Email" => sanitize_email($order_data['billing']['email']),
                );
                
                $order_country_code = sanitize_text_field($order_data['billing']['country']);
            }

            $body = array(
                "OrderNumber" => sanitize_text_field($order_data['id']),
                "ProductList" => $products,
                "Currency" => sanitize_text_field($order_data['currency']),
                "TotalAmount" => sanitize_text_field($total),
                "TotalDiscount" => sanitize_text_field($order_data['discount_total']),
                "CreditAmount" => 0.0,
                "ShipmentAmount" => sanitize_text_field($shipping_cost),
                "ShipmentType" => "Standard",
                "ShipmentVatRates" => array(
                    $shipping_vat_rate
                ) ,
                "ShippingAddress" => $shipping,
                "BillingAddress" => $billing,
                "ResponseToMerchantUrl" => sanitize_text_field($success_page),
                "RecoveryUrl" => sanitize_text_field($site_url),
                "OrderCountryCode" => sanitize_text_field($order_country_code),
                "Note" => sanitize_textarea_field($order_data['customer_note']),
                "PaymentAction" => 1,
                "PaymentType" => 5,
                "InvoiceCallbackEndpoint" => '',
            );
			
            $body = json_encode($body);
            
            $token = $this->token;
            $token = base64_encode($token);

            $headers = array(
            	'Content-Type' => 'application/json',
            	'Authorization' => 'Basic '.$token
            );
			
			$response = wp_remote_post($endpoint, array(
				'method' => 'POST',
				'timeout'     => 60, // added
				'redirection' => 5,  // added
				'blocking'    => true, // added
				'httpversion' => '1.0',
				'data_format' => 'body',
				'sslverify' => false,
				'headers' => $headers,
				'body' => $body
				)
			);
			
			$results = json_decode(wp_remote_retrieve_body($response), true);
			
            if (array_key_exists('HostedPageUrl', $results))
            {
                // Mark as on-hold (we're awaiting the cheque)
                $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));

				$HostedPageUrl = sanitize_text_field($results['HostedPageUrl']);

                return array(
                    'result' => 'success',
                    'redirect' => $HostedPageUrl
                );
            }
            else
            {
            	wc_add_notice($results['ErrorMessage'], 'error');
                return;
            }

        }

        /*
         * In case you need a webhook, like PayPal IPN etc
        */
        public function webhook()
        {
        	$getsuccess = sanitize_text_field($_GET['success']);
        	$getordernumber = sanitize_text_field($_GET['orderNumber']);
        	$success = $getsuccess;
        	$paymentconfirmed = $success;
        	if($paymentconfirmed == 'True') {
				$order = wc_get_order($getordernumber);
				$order->payment_complete();
				$order->reduce_order_stock();

				update_option('webhook_debug', $_GET);
			
				if($this->successpage != '') {
					$redirect_url = $this->successpage;
				} else {
					$redirect_url = $this->get_return_url($order);
				}
            } else {
            	if($this->errorpage != '') {
					$redirect_url = $this->errorpage;
				} else {
					$redirect_url = get_site_url();
				}
            }
            echo esc_url($redirect_url);
			die();
        }

    }
}