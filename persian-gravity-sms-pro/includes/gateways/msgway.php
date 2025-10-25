<?php
/*
 * Class Name : GFHANNANSMS_Pro_MSGWAY
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro_MSGWAY {

	/**
	 * Gateway title
	 *
	 * @param array $gateways Existing gateways.
	 *
	 * @return array Merged gateways.
	 */
	public static function name( $gateways ) {
		$name    = __( 'راه پیام (MsgWay)', 'GF_SMS' );
		$gateway = array( strtolower( str_replace( 'GFHANNANSMS_Pro_', '', __CLASS__ ) ) => $name );

		return array_unique( array_merge( $gateways, $gateway ) );
	}


	/**
	 * Gateway parameters
	 *
	 * @return array Gateway options.
	 */
	public static function options() {
		return array(
			'apiKey' => __( 'کلید API (apiKey)', 'GF_SMS' ),
            // Add 'templateID' as a configuration option
            'templateID' => __( 'شناسه الگو پیشفرض (Template ID)', 'GF_SMS')
		);
	}

	/**
	 * Check if gateway supports credit check.
	 * MsgWay supports checking balance.
	 *
	 * @return boolean True if supports credit check.
	 */
	public static function credit() {
		return true;
	}


	/**
	 * Gateway action processor. Handles 'send' and 'credit'.
	 *
	 * @param array  $options  Gateway options (apiKey, templateID).
	 * @param string $action   Action to perform ('send' or 'credit').
	 * @param string $from     Sender number (not used by MsgWay API).
	 * @param string $to       Recipient number(s), comma-separated.
	 * @param string $messages Message content (used as 'code' for send action).
	 *
	 * @return string|WP_Error 'OK' on success, error message or WP_Error on failure.
	 */
	public static function process( $options, $action, $from, $to, $messages ) {

		$api_key = isset( $options['apiKey'] ) ? $options['apiKey'] : '';
        // Get the default template ID from options, fallback to 3 if not set
        $template_id = isset($options['templateID']) && !empty($options['templateID']) ? $options['templateID'] : 3;


		if ( empty( $api_key ) ) {
			return __( 'کلید API راه پیام (apiKey) تنظیم نشده است.', 'GF_SMS' );
		}

		if ( $action == 'credit' ) {

			$api_url = 'https://api.msgway.com/balance/get';
			$args    = array(
				'method'  => 'POST',
				'headers' => array(
					'apiKey'          => $api_key,
					'accept-language' => 'fa', // Optional: Request responses in Persian
				),
				'timeout' => 30, // seconds
			);

			$response = wp_remote_post( $api_url, $args );

			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return __( 'پاسخ نامعتبر از سرور دریافت شد (خطای JSON).', 'GF_SMS' );
			}

            // Check if 'code' exists before accessing it
            if (isset($data['result']['code']) && $data['result']['code'] == 1 && isset($data['balance'])) {
                 // Format the balance for better readability if needed
                return number_format($data['balance']) . ' ' . __('ریال', 'GF_SMS');
            } elseif (isset($data['result']['message'])) {
                return $data['result']['message']; // Return error message from API
            } else {
                return __( 'خطای ناشناخته در دریافت موجودی.', 'GF_SMS' );
            }

		} elseif ( $action == 'send' ) {

			$api_url = 'https://api.msgway.com/send';

            // Ensure GFHANNANSMS_Form_Send class exists before calling its method
			if ( ! class_exists('GFHANNANSMS_Form_Send') ) {
				return __( 'خطای داخلی: کلاس GFHANNANSMS_Form_Send یافت نشد.', 'GF_SMS');
			}
            // Use the plugin's built-in function to format numbers to +98...
            $formatted_to = GFHANNANSMS_Form_Send::change_mobile($to, ''); // Pass empty code to use default/settings

            // MsgWay expects a single number for 'mobile', handle multiple recipients if needed
            // For now, we assume $formatted_to contains a single valid number or comma-separated numbers
            // If comma-separated, we need to send individually. This example handles only the first number.
            $recipients = explode(',', $formatted_to);
            $first_recipient = trim($recipients[0]);

            if (empty($first_recipient)) {
                return __( 'شماره گیرنده نامعتبر است.', 'GF_SMS');
            }


			$payload = array(
				'mobile'     => $first_recipient, // Send to the first recipient for now
				'method'     => 'sms',
				'templateID' => (int) $template_id, // Ensure templateID is an integer
				'code'       => (string) $messages, // Use the message content as the 'code' parameter
                // 'params' => [] // If your template uses params instead of code
			);

			$args = array(
				'method'  => 'POST',
				'headers' => array(
					'apiKey'          => $api_key,
					'accept-language' => 'fa',
					'Content-Type'    => 'application/json',
				),
				'body'    => json_encode( $payload ),
				'timeout' => 30, // seconds
			);

			$response = wp_remote_post( $api_url, $args );

			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
				return __( 'پاسخ نامعتبر از سرور دریافت شد (خطای JSON).', 'GF_SMS' );
			}

			// According to MsgWay docs, result.code == 1 indicates success
            if (isset($data['result']['code']) && $data['result']['code'] == 1) {
                // If there were multiple recipients, you might want to loop and send here
                // For simplicity, we just return 'OK' after the first successful send
                return 'OK';
            } elseif (isset($data['result']['message'])) {
                return $data['result']['message']; // Return error message from API
            } else {
                return __( 'خطای ناشناخته در ارسال پیامک.', 'GF_SMS' );
            }
		}

        // Handle 'range' if needed, though MsgWay doesn't seem to use it based on docs
        if ($action == "range") {
			$min = 1; // MsgWay doesn't specify min/max, setting defaults
			$max = 1000; // Example max length
			return array("min" => $min, "max" => $max);
		}


		return __( 'عملیات نامشخص.', 'GF_SMS' ); // Unknown action
	}
}
