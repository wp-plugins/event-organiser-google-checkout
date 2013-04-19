<?php 
/*
Plugin Name: Event Organiser Google Checkout
Plugin URI: http://www.wp-event-organiser.com
Version: 1.0
Description: Adds Google Checkout to Event Organiser Pro
Author: Stephen Harris
Author URI: http://www.stephenharris.info
*/
/*  Copyright 2013 Stephen Harris (contact@stephenharris.info)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

/*
 * The following requries Event Organiser Pro 1.0.1+
 * Event Organiser Pro requires Event Organiser 2.0+
 */

/**
 * Register the gateway
 * @param array $gateways Array of gateways
 * @return  Array of gateways with Google checkout added
 */
function eventorganiser_gc_add_google_checkout( $gateways ){
	$gateways['google'] = __( 'Google Checkout', 'eventorganiserp' );
	return $gateways;
}
add_filter( 'eventorganiser_gateways', 'eventorganiser_gc_add_google_checkout' );


/**
 * Remove gateways we do not want publically available
 * @param array $gateways Array of gateways
 * @return  Array of gateways
 */
    function eventorganiser_gc_set_google_checkout_status( $gateways ){
        $options = get_option( 'eventorganiser_gc_settings' );
        if( isset( $gateways['google'] ) && $options['google_live_status'] == -1 ){
             unset( $gateways['google'] );
        }
        return $gateways;
    }
add_filter( 'eventorganiser_enabled_gateways', 'eventorganiser_gc_set_google_checkout_status' );


/**
 * Regsister the settings & settings section
 * @uses register_setting
 */
function eventorganiser_gc_register_settings( $tab_id ) {
	register_setting( 'eventorganiser_bookings', 'eventorganiser_gc_settings' );
}
add_action( "eventorganiser_register_tab_bookings", 'eventorganiser_gc_register_settings', 20 );


/**
 * Adds the Google Checkout settings section
 * @uses add_settings_section
 */
function eventorganiser_gc_add_settings(){

	add_settings_section( 
		'eventorganiser_google_checkout_section', //Unique ID for our section
		__( 'Google Checkout', 'eventorganiserp' ), 
		'eventorganiser_gc_settings_section_text',  
		'eventorganiser_bookings' //bookings page 
	);
	
	/* Get options, and parse with default values */
	$options = wp_parse_args( get_option( 'eventorganiser_gc_settings' ),
		array(
			'google_live_status' => 0,
			'google_email' => '',
			'merchant_id' => '',
			'merchant_key' => '',
	));
	
	/* Addings a drop-down to swith the status of our payment gateway: 1 = Live, 0 = Sandbox, -1 = Disabled */
	add_settings_field(
		'google_live_status',//Unique ID for field
		__( 'Live Switch', 'eventorganiserp' ),
		'eventorganiser_select_field' ,
		'eventorganiser_bookings', //Field is on bookigns page
		'eventorganiser_google_checkout_section', //Field is in our Google section
		array(
			'label_for'=>'google_live_status',
			'name'=>'eventorganiser_gc_settings[google_live_status]',
			'options'=>array(
				'1'=>__( 'Live', 'eventorganiser' ),
				'0'=>__( 'Sandbox Mode', 'eventorganiser' ),
				'-1'=>__( 'Disable', 'eventorganiser' ),
			),
			'selected' => $options['google_live_status'],
		)
	);
	
	/* Addings a text input for Google email */
	add_settings_field(
		'eo_google_merchant_id', 
		__('Merchant ID','eventorganiserp'), 
		'eventorganiser_text_field', 
		'eventorganiser_bookings', //Field is on bookigns page
		'eventorganiser_google_checkout_section', //Field is in our Google section
		array(
			'label_for'=>'eo_google_merchant_id',
			'name'=> 'eventorganiser_gc_settings[merchant_id]',
			'value' => $options['merchant_id']
		)
	);
	
	add_settings_field(
		'eo_google_merchant_key',
		__('Merchant Key','eventorganiserp'),
		'eventorganiser_text_field',
		'eventorganiser_bookings', //Field is on bookigns page
		'eventorganiser_google_checkout_section', //Field is in our Google section
		array(
			'label_for'=>'eo_google_merchant_key',
			'name'=> 'eventorganiser_gc_settings[merchant_key]',
			'value' => $options['merchant_key']
		)
	);
}
add_action( "load-settings_page_event-settings", 'eventorganiser_gc_add_settings', 20, 0 );

/**
 * Displays text at the beggining of the settings ection
 */
function eventorganiser_gc_settings_section_text(){
	
	printf( 
		'<p> %s </p> <code>%s</code> <p>%s</p>',
		__( 'For Google checkout to work properly you shall need to set the <strong>API callback URL</strong> in your Google merchant account settings to:', 'eventorganiserp' ),
		add_query_arg( array( 'eo-listener' => 'ipn', 'eo-gateway' => 'google' ), home_url() ),
		__( 'To do this, sign into your merchant account, click the <em>Settings tab</em>, then <em>integration</em>', 'eventorganiserp' )
		.' '.__( 'Your merchant ID and key shall also be displayed on that page', 'eventorganiserp' )
	);
}

/**
 * Hooked onto eventorganiser_pre_gateway_booking_google, fired when a booking is made using Google checkout.
 * We set up the booking card and redirect the user to Google.
 * 
 * @param int $booking_id
 * @param array $booking
 */
function eventorganiser_gc_handle_booking_submission( $booking_id, $booking ){
	$google = new EO_Gateway_Google_Checkout();
	$google->booking_cart( $booking_id, $booking );
}
add_action( 'eventorganiser_pre_gateway_booking_google', 'eventorganiser_gc_handle_booking_submission', 10, 2 );


/**
 * Hooked onto eventorganiser_gateway_listener_google_ipn
 * Triggered when gateway responds. We parse the response and act accordingly (confirm booking / set it to pending). 
 */
function eventorganiser_gc_ipn_listener(){	
	$google = new EO_Gateway_Google_Checkout();
	$google->handle_ipn();
}
add_action( 'eventorganiser_gateway_listener_google_ipn', 'eventorganiser_gc_ipn_listener' );


/**
 * Basic Google Checkout helper class
 */
class EO_Gateway_Google_Checkout {

	/* Live / Test mode */
	private $is_live = false;

	/* Credentials */
	private $merchant_id = false;
	private $merchant_key = false;

	/* Google urls */
	private $sandbox_ep = 'https://sandbox.google.com/checkout/api/checkout/v2/merchantCheckout/Merchant/';
	private $sandbox_report = 'https://sandbox.google.com/checkout/api/checkout/v2/reports/Merchant/';
	private $sandbox_request = 'https://sandbox.google.com/checkout/api/checkout/v2/request/Merchant/';

	private $live_ep ='https://checkout.google.com/api/checkout/v2/merchantCheckout/Merchant/';
	private $live_report = 'https://checkout.google.com/api/checkout/v2/reports/Merchant/';
	private $live_request = 'https://checkout.google.com/api/checkout/v2/request/Merchant/';

	var $ep=false;
	var $report_ep=false;
	var $request_ep=false;

	var $cart=false;

	/**
	 * Class constructor. Sets up credentials stored in database. Set live/sandbox mode.
	 */
	function __construct() {
		
		$options = get_option( 'eventorganiser_gc_settings' );

		$this->merchant_id=  !empty( $options['merchant_id'] ) ? $options['merchant_id']  : false;
		$this->merchant_key=  !empty( $options['merchant_key'] ) ? $options['merchant_key']  : false;
		
		$is_live = isset( $options['google_live_status'] ) ? (int) $options['google_live_status'] :  -1;
		$this->is_live = ($is_live === 1);

		if( $this->is_live ){
			$this->ep = $this->live_ep.$this->merchant_id;
			$this->report_ep = $this->live_report.$this->merchant_id;
			$this->request_ep = $this->live_request.$this->merchant_id;
		}else{
			$this->ep = $this->sandbox_ep.$this->merchant_id;
			$this->report_ep = $this->sandbox_report.$this->merchant_id;
			$this->request_ep = $this->sandbox_request.$this->merchant_id;
		}
	}

	/**
	 * Sets up the Google cart for this booking
	 *  
	 * @param int $booking_id
	 * @param array $booking
	 */
	function booking_cart( $booking_id, $booking ) {

		$currency = eventorganiser_pro_get_option('currency');

		$i=1;
		$event = get_post( $booking['event_id'] );
		$tickets = eo_get_booking_tickets( $booking_id );

		$ticket_quantity = 0;
		$items = array();
		if( $tickets ){
			foreach ( $tickets as $ticket ) {
				//Add each ticket type bought as an item
				$items[] = array(
						'item-name' => esc_html( $event->post_title.' ('.$ticket->ticket_name.')' ),
						'item-description' => $ticket->ticket_name,
						'unit-price' => array('@attributes'=>'currency="'.$currency.'"',$ticket->ticket_price),
						'quantity' => intval( $ticket->ticket_quantity ),
						'digital-content' => array(
								'description' =>'You shall be e-mailed your tickets once payment has been confirmed',
						),
				);
				$ticket_quantity =+ (int) $ticket->ticket_quantity;
			}
		}
			
		//Form cart
		$cart['checkout-shopping-cart'] = array(
				'@attributes'=>'xmlns="http://checkout.google.com/schema/2"',
				'shopping-cart'=>
				array(
						'items'=>array(
								'item' => $items
						),
						'merchant-private-data' =>array(
								'booking_id' => $booking_id,
								'event_id' => $booking['event_id'],
								'occurrence_id' => $booking['occurrence_id'],
								'booking_user' => $booking['booking_user'],
								'ticket_quantity' => $ticket_quantity,
						)
				),
				'order-processing-support'=>array(
						'request-initial-auth-details' => 'true',
				),
				'checkout-flow-support'=>array(
						'merchant-checkout-flow-support' => array(
								'continue-shopping-url' => add_query_arg( 'payment-confirmation', 'google', get_permalink( $booking['event_id'] ) ),
						),
				),
		);
		
		//Filter the cart for plug-ins
		$cart = apply_filters( 'eventorganiser_pre_gateway_checkout_google', $cart, $booking );
		
		//Send to gateway
		$error = $this->post_to_gateway( $cart );
		
		//If we get this far there was an error
		wp_die( $error->get_error_message() );
	}

	
	/**
	 * Redirects user to gateway.
	 * Returns WP_Error object if there's an error
	 * @param array $cart
	 */
	function post_to_gateway( $cart ) {

		$response = $this->request( $this->ep, $cart );

		if( is_wp_error( $response ) )
			return $response;

		if( isset( $response['checkout-redirect'] ) && isset( $response['checkout-redirect']["redirect-url"] ) ){
			$redirect_url = esc_url_raw( urldecode( $response['checkout-redirect']["redirect-url"] ) );
			wp_redirect( $redirect_url );
			exit();
		}else{
			return new WP_Error( 'unknown', 'Uknown error' );
		}
	}


	/**
	 * Deals with interacting with Google Checkout.
	 * Parses an array into XML and sends this to Google
	 * Sets headers and deals with response
	 *
	 *@uses wp_remote_post
	 *@param (string) the url to send the request to
	 *@param (array) an array which is to be parsed into XML
	 *@return (array|WP_Error) If a valid response is received this is parsed into an array. Otherwise a WP_Error object is returned
	 */
	function request( $url, $post_args ){

		//Parse array into XML
		$xml = $this->array_to_xml($post_args);

		$params = array(
				'body'		=> $xml,
				'headers' => array(
						'Authorization' => 'Basic '.base64_encode($this->merchant_id.':'.$this->merchant_key),
						'Content-Type'=> 'application/xml; charset=UTF-8',
						'Accept'=>'application/xml; charset=UTF-8'
				),
				'sslverify' => true,
				'timeout' 	=> 300,
		);
		$resp = wp_remote_post( $url, $params );

		//Error with the making of the request / recieving the response
		if( is_wp_error($resp) )
			return $resp;


		$raw_xml = wp_remote_retrieve_body($resp);
		if (get_magic_quotes_gpc()) {
			$raw_xml = stripslashes($raw_xml);
		}

		$object = new SimpleXMLElement($raw_xml);
		$array = json_decode(json_encode($object), true);
		$response = array();
		$response[$object->getName()] = $array;


		//Error reported by Google
		if( '200' != wp_remote_retrieve_response_code($resp) ){
			if( isset($response['error']) && isset($response['error']['error-message']) ){
				$message = $response['error']['error-message'];

			}else{
				$message = 'Unknown Error';
			}
			return new WP_Error(wp_remote_retrieve_response_code($resp), $message);
		}

		return $response;
	}


	/**
	 * Sends a request for a notification by serial number
	 *
	 *@param (string) serial number
	 *@param (array|WP_Error) The notification (as an array) or a WP_Error object on error
	 */
	function send_notification_history_request( $serial_number=''){

		$notification_request['notification-history-request'] = array(
				'@attributes'=>'xmlns="http://checkout.google.com/schema/2"',
				'serial-number' => $serial_number,
		);

		return $this->request($this->report_ep,$notification_request);
	}


	/**
	 * A method that detals with Google payment notifications
	 *
	 **/
	function handle_ipn(){

		if( !isset( $_POST['serial-number'] ) )
			return;

		$serial_number = $_POST['serial-number'];

		//Request notification
		$response = $this->send_notification_history_request( $serial_number );

		if( is_wp_error($response) ){
			//Log error
			$code = $response->get_error_code();
			exit();
		}

		$this->sand_ack($serial_number,false);

		//Get notification type
		$log = array();
		reset($response);
		$type = key($response);

		switch($type){
			case "new-order-notification":
				//New order
				//Merchant data: (store for charge-amount-nofication ? ).
				//Update with google order number
				$data = $response[$type]['shopping-cart']['merchant-private-data'];
				$google_order_number = $response[$type]['google-order-number'];
				$booking_id = $data['booking_id'];
				
				//We store the google order number withthe booking so that we can 
				//find it later when we need to confirm the booking.
				//For some reason Google doesn't send us the booking ID when its confirmed.
				update_post_meta( $booking_id, '_eo_booking_transaction_id', $google_order_number );
				break;

			case "authorization-amount-notification":
				//Charge the order
				$google_order_number = $response[$type]['google-order-number'];
				$response = $this->send_charge_order( $google_order_number );
				exit();
				break;

			case "charge-amount-notification":
				//Payment confirmed.
								
				//Retrieve booking by google order number
				$order_number = $response[$type]['google-order-number'];
				$bookings = eventorganiser_get_bookings( array(
							'status' => 'any',
							'fields' => 'ids',
							'numberposts' => 1,
							'meta_key' => '_eo_booking_transaction_id',
							'meta_value' => $order_number
						));
						
				if( $bookings ){
					$booking_id = array_shift( $bookings );

					$log = array(
						'raw_response' => serialize( $response ),
						'timestamp' => time(),
					);
					
					do_action( 'eventorganiser_gateway_notification_transaction', $booking_id, $log );
				}
			break;

			//Currently unsupported
			case "refund-amount-notification":
			case "cancelled-subscription-notification":
			case "order-state-change-notification":
			case "risk-information-notification":
				exit();
			break;

			case "chargeback-amount-notification":
			case "order-numbers":
			case "invalid-order-numbers":
			default:
				break;
		}
		exit();
	}


	/**
	 * Charges an order.
	 * When we recieve a authorization-amount-notification the order has been authorised to be charged.
	 * This method tells Google to charge the buyer, we should then recieve a charge-amount-notification when payment has been confirmed.
	 * Once we recieve the charge-amount-notification we confirm the booking
	 *
	 *@param (string) A Google order number
	 */
	function send_charge_order( $order_number ){

		$charge_order['charge-order'] = array(
				'@attributes'=>'xmlns="http://checkout.google.com/schema/2" google-order-number="'.$order_number.'"'
		);
		return $this->request($this->request_ep,$charge_order);
	}

	/**
	 * Tells Google that we recieved the notification
	 *
	 *@param (string) the serial number of the notification
	 *@param (bool) If true kills any further processing. Otherwise just prints the response (nothing else should be printed after this).
	 */
	function sand_ack($serial=null, $die=true) {
		header('HTTP/1.0 200 OK');

		$acknowledgment = '<?xml version="1.0" encoding="UTF-8"?>' .
				'<notification-acknowledgment xmlns="http://checkout.google.com/schema/2"';

		if( isset($serial) ) {
			$acknowledgment .=' serial-number="'.$serial.'"';
		}

		$acknowledgment .= " />";

		if( $die ){
			die($acknowledgment);
		}else{
			echo $acknowledgment;
		}
	}


	/**
	 * Converts an array to XML
	 */
	function array_to_xml($data, $xml='',$level=0){

		if( $level == 0 ){
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		}

		$indent = ( intval($level) ?  implode('',array_fill(0,$level,'   ')) : '');

		foreach ($data as $key => $value ){

			$attributes='';

			if( is_array($value) ){
				if( isset($value['@attributes']) ){
					$attributes = ' '.$value['@attributes'];
					unset($value['@attributes']);
				}
			}

			if( empty($value) ){
				$xml .= $indent."<{$key}{$attributes}></{$key}>\n";

			}elseif( is_array($value) && eventorganiser_is_associative($value) ){

				$xml .= $indent ."<{$key}{$attributes}>\n".$this->array_to_xml( $value, '',$level+1).$indent."</{$key}>\n";

			}else{
				$values = (array) $value;
				foreach( $values as $val ){
					if( is_array($val) ){
						$xml .= $indent ."<{$key}{$attributes}>\n".$this->array_to_xml( $val, '',$level+1).$indent."</{$key}>\n";
					}else{
						$val = trim(esc_html($val));
						$xml .= $indent."<{$key}{$attributes}>{$val}</{$key}>\n";
					}
				}
			}
		}
		return $xml;
	}
}//END class

?>