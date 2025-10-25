<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Fix for 'Cannot declare class' fatal error
if ( ! class_exists( 'GFHANNANSMS_Pro_MSGWAY' ) ) {

	class GFHANNANSMS_Pro_MSGWAY {

		public static function name( $gateways ) {
			$gateways['msgway'] = __( 'راه پیام (MsgWay)', 'GF_SMS' );

			return $gateways;
		}

		public static function options() {
			return array(
				'apiKey'     => __( 'API Key', 'GF_SMS' ),
				'templateID' => __( 'Default Template ID (Optional)', 'GF_SMS' ),
			);
		}

		public static function credit() {
			return true;
		}

		public static function process( $options, $action, $from, $to, $messages ) {

			$apiKey = ! empty( $options['apiKey'] ) ? $options['apiKey'] : '';

			if ( empty( $apiKey ) ) {
				return __( 'API Key is not set for MsgWay.', 'GF_SMS' );
			}

			$api_url_base = 'https://api.msgway.com';
			$headers      = array(
				'apiKey'       => $apiKey,
				'Content-Type' => 'application/json'
			);

			// Action: Check Credit
			if ( $action == 'credit' ) {

				$api_url  = $api_url_base . '/balance/get';
				$response = wp_remote_post( $api_url, array(
					'headers' => $headers,
					'body'    => json_encode( array() ), // Empty body as per docs
					'timeout' => 20
				) );

				if ( is_wp_error( $response ) ) {
					return $response->get_error_message();
				}

				$body      = wp_remote_retrieve_body( $response );
				$data      = json_decode( $body, true );
				$http_code = wp_remote_retrieve_response_code( $response );

				if ( $http_code == 200 && isset( $data['balance'] ) ) {
					return $data['balance'];
				} elseif ( isset( $data['message'] ) ) {
					return $data['message'];
				} else {
					return __( 'Could not check MsgWay credit.', 'GF_SMS' );
				}
			}

			// Action: Send SMS
			if ( $action == 'send' ) {

				// Ensure the 'send' class is available
				if ( ! class_exists( 'GFHANNANSMS_Form_Send' ) ) {
					require_once( GF_SMS_DIR . 'includes/send.php' );
				}

				// Format the recipient number
				// [0] is used because the $to variable from the original plugin is an array
				$to = GFHANNANSMS_Form_Send::change_mobile( $to[0] ); 

				// Get Template ID from settings, default to 3 if not set (as per API suggestion)
				$templateID = ! empty( $options['templateID'] ) ? $options['templateID'] : '3';

				// MsgWay API expects the message content in the 'code' parameter for template sends
				$body_args = array(
					'method'     => 'sms',
					'mobile'     => $to,
					'templateID' => $templateID,
					'code'       => $messages, // Send the message as the 'code' parameter
				);

				$api_url  = $api_url_base . '/send';
				$response = wp_remote_post( $api_url, array(
					'headers' => $headers,
					'body'    => json_encode( $body_args ),
					'timeout' => 20
				) );

				if ( is_wp_error( $response ) ) {
					return $response->get_error_message();
				}

				$body      = wp_remote_retrieve_body( $response );
				$data      = json_decode( $body, true );
				$http_code = wp_remote_retrieve_response_code( $response );

				// Check for success (Code 200 and 'OK' message)
				if ( $http_code == 200 && isset( $data['message'] ) && $data['message'] == 'OK' ) {
					return 'OK'; // Success
				} elseif ( isset( $data['message'] ) ) {
					return $data['message']; // Return error message from API
				} else {
					return __( 'An unknown error occurred with MsgWay.', 'GF_SMS' );
				}
			}

			return false; // Default return if no action matched
		}
	}
} // End if class_exists

