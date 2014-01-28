<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Name: WooCommerce CoinPayments.net Gateway
 * Plugin URI: https://www.coinpayments.net/
 * Description:  Provides a CoinPayments.net Payment Gateway.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 1.0.1
 */

/**
 * CoinPayments.net Gateway
 * Based on the PayPal Standard Payment Gateway
 *
 * Provides a CoinPayments.net Payment Gateway.
 *
 * @class 		WC_Coinpayments
 * @extends		WC_Gateway_Coinpayments
 * @version		1.0.1
 * @package		WooCommerce/Classes/Payment
 * @author 		CoinPayments.net based on PayPal module by WooThemes
 */

add_action( 'plugins_loaded', 'coinpayments_gateway_load', 0 );
function coinpayments_gateway_load() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        // oops!
        return;
    }

    /**
     * Add the gateway to WooCommerce.
     */
    add_filter( 'woocommerce_payment_gateways', 'wccoinpayments_add_gateway' );

    function wccoinpayments_add_gateway( $methods ) {
    	if (!in_array('WC_Gateway_Coinpayments', $methods)) {
				$methods[] = 'WC_Gateway_Coinpayments';
			}
			return $methods;
    }


    class WC_Gateway_Coinpayments extends WC_Payment_Gateway {

	var $ipn_url;

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
	public function __construct() {
		global $woocommerce;

        $this->id           = 'coinpayments';
        $this->icon         = apply_filters( 'woocommerce_coinpayments_icon', plugins_url().'/coinpayments-payment-gateway-for-woocommerce/assets/images/icons/coinpayments.png' );
        $this->has_fields   = false;
        $this->method_title = __( 'CoinPayments.net', 'woocommerce' );
        $this->ipn_url   = add_query_arg( 'wc-api', 'WC_Gateway_Coinpayments', home_url( '/' ) );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->merchant_id 			= $this->get_option( 'merchant_id' );
		$this->ipn_secret   = $this->get_option( 'ipn_secret' );
		$this->send_shipping	= $this->get_option( 'send_shipping' );
		$this->debug_email			= $this->get_option( 'debug_email' );
		$this->form_submission_method = $this->get_option( 'form_submission_method' ) == 'yes' ? true : false;
		$this->invoice_prefix	= $this->get_option( 'invoice_prefix', 'WC-' );

		// Logs
		$this->log = $woocommerce->logger();

		// Actions
		add_action( 'woocommerce_receipt_coinpayments', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_coinpayments', array( $this, 'check_ipn_response' ) );

		if ( !$this->is_valid_for_use() ) $this->enabled = false;
    }


    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use() {
        //if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_coinpayments_supported_currencies', array( 'AUD', 'CAD', 'USD', 'EUR', 'JPY', 'GBP', 'CZK', 'BTC', 'LTC' ) ) ) ) return false;
        // ^- instead of trying to maintain this list just let it always work
        return true;
    }

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

		?>
		<h3><?php _e( 'CoinPayments.net', 'woocommerce' ); ?></h3>
		<p><?php _e( 'Completes checkout via CoinPayments.net', 'woocommerce' ); ?></p>

    	<?php if ( $this->is_valid_for_use() ) : ?>

			<table class="form-table">
			<?php
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
			?>
			</table><!--/.form-table-->

		<?php else : ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'CoinPayments.net does not support your store currency.', 'woocommerce' ); ?></p></div>
		<?php
			endif;
	}


    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {

    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable CoinPayments.net', 'woocommerce' ),
							'default' => 'yes'
						),
			'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'CoinPayments.net', 'woocommerce' ),
							'desc_tip'      => true,
						),
			'description' => array(
							'title' => __( 'Description', 'woocommerce' ),
							'type' => 'textarea',
							'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'woocommerce' )
						),
			'merchant_id' => array(
							'title' => __( 'Merchant ID', 'woocommerce' ),
							'type' 			=> 'text',
							'description' => __( 'Please enter your CoinPayments.net Merchant ID.', 'woocommerce' ),
							'default' => '',
						),
			'ipn_secret' => array(
							'title' => __( 'IPN Secret', 'woocommerce' ),
							'type' 			=> 'text',
							'description' => __( 'Please enter your CoinPayments.net IPN Secret.', 'woocommerce' ),
							'default' => '',
						),
			'invoice_prefix' => array(
							'title' => __( 'Invoice Prefix', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'Please enter a prefix for your invoice numbers. If you use your CoinPayments.net account for multiple stores ensure this prefix is unique.', 'woocommerce' ),
							'default' => 'WC-',
							'desc_tip'      => true,
						),
			'testing' => array(
							'title' => __( 'Gateway Testing', 'woocommerce' ),
							'type' => 'title',
							'description' => '',
						),
			'debug_email' => array(
							'title' => __( 'Debug Email', 'woocommerce' ),
							'type' => 'email',
							'default' => '',
							'description' => __( 'Send copies of invalid IPNs to this email address.', 'woocommerce' ),
						)
			);

    }


	/**
	 * Get CoinPayments.net Args
	 *
	 * @access public
	 * @param mixed $order
	 * @return array
	 */
	function get_coinpayments_args( $order ) {
		global $woocommerce;

		$order_id = $order->id;

		if ( in_array( $order->billing_country, array( 'US','CA' ) ) ) {
			$order->billing_phone = str_replace( array( '( ', '-', ' ', ' )', '.' ), '', $order->billing_phone );			
		}

		// CoinPayments.net Args
		$coinpayments_args = array(
				'cmd' 					=> '_pay',
				'merchant' 				=> $this->merchant_id,
				'allow_extra' 				=> 0,
				'currency' 		=> get_woocommerce_currency(),
				'reset' 				=> 1,
				'success_url' 				=> add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ),
				'cancel_url'			=> $order->get_cancel_order_url(),

				// Order key + ID
				'invoice'				=> $this->invoice_prefix . ltrim( $order->get_order_number(), '#' ),
				'custom' 				=> serialize( array( $order_id, $order->order_key ) ),

				// IPN
				'ipn_url'			=> $this->ipn_url,

				// Billing Address info
				'first_name'			=> $order->billing_first_name,
				'last_name'				=> $order->billing_last_name,
				'address1'				=> $order->billing_address_1,
				'address2'				=> $order->billing_address_2,
				'city'					=> $order->billing_city,
				'state'					=> $order->billing_state,
				'zip'					=> $order->billing_postcode,
				'country'				=> $order->billing_country,
				'email'					=> $order->billing_email,
				'phone'					=> $order->billing_phone,
		);

		if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
			$coinpayments_args['item_name'] 	= sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() );
			$coinpayments_args['quantity'] 		= 1;
			$coinpayments_args['taxf'] 				= 0.00;
			$coinpayments_args['amountf'] 		= number_format( $order->get_total() - $order->get_shipping() - $order->get_shipping_tax(), 8, '.', '' );
			$coinpayments_args['shippingf']		= number_format( $order->get_shipping() + $order->get_shipping_tax() , 8, '.', '' );
		} else {
			$coinpayments_args['item_name'] 	= sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() );
			$coinpayments_args['quantity'] 		= 1;
			$coinpayments_args['taxf']				= $order->get_total_tax();
			$coinpayments_args['amountf'] 		= number_format( $order->get_total() - $order->get_shipping() - $order->get_total_tax(), 8, '.', '' );
			$coinpayments_args['shippingf']		= number_format( $order->get_shipping(), 8, '.', '' );
		}

		$coinpayments_args = apply_filters( 'woocommerce_coinpayments_args', $coinpayments_args );

		return $coinpayments_args;
	}


    /**
	 * Generate the coinpayments button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_coinpayments_form( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$coinpayments_adr = "https://www.coinpayments.net/index.php";

		$coinpayments_args = $this->get_coinpayments_args( $order );

		$coinpayments_args_array = array();

		foreach ($coinpayments_args as $key => $value) {
			$coinpayments_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
		}

		$woocommerce->add_inline_js( '
			jQuery("body").block({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to CoinPayments.net to make payment.', 'woocommerce' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
				        padding:        "20px",
				        zindex:         "9999999",
				        textAlign:      "center",
				        color:          "#555",
				        border:         "3px solid #aaa",
				        backgroundColor:"#fff",
				        cursor:         "wait",
				        lineHeight:		"24px",
				    }
				});
			jQuery("#submit_coinpayments_payment_form").click();
		' );

		return '<form action="'.esc_url( $coinpayments_adr ).'" method="post" id="coinpayments_payment_form" target="_top">
				' . implode( '', $coinpayments_args_array) . '
				<input type="submit" class="button alt" id="submit_coinpayments_payment_form" value="' . __( 'Pay via CoinPayments.net', 'woocommerce' ) . '" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'woocommerce' ).'</a>
			</form>';

	}


    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
		);

	}


    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
	function receipt_page( $order ) {

		echo '<p>'.__( 'Thank you for your order, please click the button below to pay with CoinPayments.net.', 'woocommerce' ).'</p>';

		echo $this->generate_coinpayments_form( $order );

	}

	/**
	 * Check CoinPayments.net IPN validity
	 **/
	function check_ipn_request_is_valid() {
		global $woocommerce;

		$order = false;
		$error_msg = "Unknown error";
		$auth_ok = false;
		
		if (isset($_POST['ipn_mode']) && $_POST['ipn_mode'] == 'hmac') {
			if (isset($_SERVER['HTTP_HMAC']) && !empty($_SERVER['HTTP_HMAC'])) {
				$request = file_get_contents('php://input');
				if ($request !== FALSE && !empty($request)) {
					if (isset($_POST['merchant']) && $_POST['merchant'] == trim($this->merchant_id)) {
						$hmac = hash_hmac("sha512", $request, trim($this->ipn_secret));
						if ($hmac == $_SERVER['HTTP_HMAC']) {
							$auth_ok = true;
						} else {
							$error_msg = 'HMAC signature does not match';
						}
					} else {
						$error_msg = 'No or incorrect Merchant ID passed';
					}
				} else {
					$error_msg = 'Error reading POST data';
				}
			} else {
				$error_msg = 'No HMAC signature sent.';
			}
		} else {
			if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && 
				$_SERVER['PHP_AUTH_USER'] == trim($this->merchant_id) && 
				$_SERVER['PHP_AUTH_PW'] == trim($this->ipn_secret)) {
				$auth_ok = true;
			} else {
				$error_msg = "Invalid merchant id/ipn secret";
			}
		}

		if ($auth_ok) {
	    if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
	    	$order = $this->get_coinpayments_order( $_POST );
	    }
				
			if ($order) {
				if ($_POST['ipn_type'] == "button") {
					if ($_POST['merchant'] == $this->merchant_id) {
						if ($_POST['currency1'] == get_woocommerce_currency()) {
							if ($_POST['amount1'] >= $order->get_total()) {
								print "IPN check OK\n";
								return true;
							} else {
								$error_msg = "Amount received is less than the total!";
							}
						} else {
							$error_msg = "Original currency doesn't match!";
						}
					} else {
						$error_msg = "Merchant ID doesn't match!";
					}
				} else {
					$error_msg = "ipn_type != button";
				}
			} else {
				$error_msg = "Could not find order info for order: ".$_POST['invoice'];
			}
		}

		$report = "AUTH User: |".$_SERVER['PHP_AUTH_USER']."|\n";
		$report .= "AUTH User: |".trim($this->merchant_id)."|\n\n";
		$report .= "AUTH Pass: |".$_SERVER['PHP_AUTH_PW']."|\n\n";
		$report .= "AUTH Pass: |".trim($this->ipn_secret)."|\n\n";
				
		$report .= "Error Message: ".$error_msg."\n\n";
				
		$report .= "POST Fields\n\n";
		foreach ($_POST as $key => $value) {
			$report .= $key.'='.$value."\n";
		}
		
		if ($order) {
			$order->update_status('on-hold', sprintf( __( 'CoinPayments.net IPN Error: %s', 'woocommerce' ), $error_msg ) );										
		}
		if (!empty($this->debug_email)) { mail($this->debug_email, "CoinPayments.net Invalid IPN", $report); }
		mail(get_option( 'admin_email' ), sprintf( __( 'CoinPayments.net Invalid IPN', 'woocommerce' ), $error_msg ), $report );
		die('Error: '.$error_msg);
		return false;
	}

	/**
	 * Successful Payment!
	 *
	 * @access public
	 * @param array $posted
	 * @return void
	 */
	function successful_request( $posted ) {
		global $woocommerce;

		$posted = stripslashes_deep( $posted );

		// Custom holds post ID
	    if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
			    $order = $this->get_coinpayments_order( $posted );

        	$this->log->add( 'coinpayments', 'Order #'.$order->id.' payment status: ' . $posted['status_text'] );

         	if ( $order->status != 'completed' ) {
         		// no need to update status if it's already done
            if ( ! empty( $posted['txn_id'] ) )
             	update_post_meta( $order->id, 'Transaction ID', $posted['txn_id'] );
            if ( ! empty( $posted['first_name'] ) )
             	update_post_meta( $order->id, 'Payer first name', $posted['first_name'] );
            if ( ! empty( $posted['last_name'] ) )
             	update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );
            if ( ! empty( $posted['email'] ) )
             	update_post_meta( $order->id, 'Payer email', $posted['email'] );

           	$order->add_order_note('CoinPayments.net Payment Status: '.$posted['status_text']);
						if ($posted['status'] >= 0 && $posted['status'] < 100) {
							//print "mark pending\n";
							$order->update_status('pending', 'CoinPayments.net Payment pending: '.$posted['status_text']);
						} else if ($posted['status'] < 0) {
							//print "mark cancelled\n";
              $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out: '.$posted['status_text']);
							mail( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s cancelled/timed out', 'woocommerce' ), $order->get_order_number() ), $posted['status_text'] );
						} else {
							//print "mark complete\n";
             	$order->payment_complete();
						}
	        }
	        die("IPN OK");
	    }
	}

	/**
	 * Check for PayPal IPN Response
	 *
	 * @access public
	 * @return void
	 */
	function check_ipn_response() {

		@ob_clean();

		if ( ! empty( $_POST ) && $this->check_ipn_request_is_valid() ) {
			$this->successful_request($_POST );
		} else {
			wp_die( "CoinPayments.net IPN Request Failure" );
 		}
	}

	/**
	 * get_coinpayments_order function.
	 *
	 * @access public
	 * @param mixed $posted
	 * @return void
	 */
	function get_coinpayments_order( $posted ) {
		$custom = maybe_unserialize( $posted['custom'] );

    	// Backwards comp for IPN requests
    	if ( is_numeric( $custom ) ) {
	    	$order_id = (int) $custom;
	    	$order_key = $posted['invoice'];
    	} elseif( is_string( $custom ) ) {
	    	$order_id = (int) str_replace( $this->invoice_prefix, '', $custom );
	    	$order_key = $custom;
    	} else {
    		list( $order_id, $order_key ) = $custom;
		}

		$order = new WC_Order( $order_id );

		if ( ! isset( $order->id ) ) {
			// We have an invalid $order_id, probably because invoice_prefix has changed
			$order_id 	= woocommerce_get_order_id_by_order_key( $order_key );
			$order 		= new WC_Order( $order_id );
		}

		// Validate key
		if ( $order->order_key !== $order_key ) {
        	exit;
        }

        return $order;
	}

}

class WC_Coinpayments extends WC_Gateway_Coinpayments {
	public function __construct() {
		_deprecated_function( 'WC_Coinpayments', '1.4', 'WC_Gateway_Coinpayments' );
		parent::__construct();
	}
}
}
