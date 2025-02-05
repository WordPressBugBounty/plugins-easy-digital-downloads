<?php
/**
 * PayPal Commerce Connect
 *
 * @package   easy-digital-downloads
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     2.11
 */

namespace EDD\Gateways\PayPal\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal;
use EDD\Gateways\PayPal\AccountStatusValidator;
use EDD\Gateways\PayPal\API;

if ( ! defined( 'EDD_PAYPAL_PARTNER_CONNECT_URL' ) ) {
	define( 'EDD_PAYPAL_PARTNER_CONNECT_URL', 'https://easydigitaldownloads.com/wp-json/paypal-connect/v1/' );
}

/**
 * Returns the content for the PayPal Commerce Connect fields.
 *
 * If the account is not yet connected, the user is shown a "Connect with PayPal" button.
 * If they are connected, their account details are shown instead.
 *
 * @since 2.11
 * @return void
 */
function connect_settings_field() {
	$is_connected = PayPal\has_rest_api_connection();
	$mode         = edd_is_test_mode() ? __( 'sandbox', 'easy-digital-downloads' ) : __( 'live', 'easy-digital-downloads' );

	if ( ! $is_connected ) {
		$onboarding_data = get_onboarding_data();
		if ( 200 !== $onboarding_data['code'] || empty( $onboarding_data['body']->signupLink ) ) {
			?>
			<div class="notice notice-error inline">
				<p>
					<?php
					echo wp_kses(
						sprintf(
						/* translators: 1. opening <strong> tag, 2. closing </strong> tag */
							__( '%1$sPayPal Communication Error:%2$s We are having trouble communicating with PayPal at the moment. Please try again later, and if the issue persists, reach out to our support team.', 'easy-digital-downloads' ),
							'<strong>',
							'</strong>'
						),
						array( 'strong' => array() )
					);
					?>
				</p>
			</div>
			<?php
		} else {
			?>
			<a type="button" target="_blank" id="edd-paypal-commerce-link" class="button button-secondary" href="<?php echo $onboarding_data['body']->signupLink; ?>&displayMode=minibrowser" data-paypal-onboard-complete="eddPayPalOnboardingCallback" data-paypal-button="true" data-paypal-onboard-button="true" data-nonce="<?php echo esc_attr( wp_create_nonce( 'edd_process_paypal_connect' ) ); ?>">
				<?php
				/* translators: %s: the store mode, either `sandbox` or `live` */
				printf( esc_html__( 'Connect with PayPal in %s mode', 'easy-digital-downloads' ), esc_html( $mode ) );
				?>
			</a>
			<?php
		}
		?>
		<div id="edd-paypal-commerce-errors"></div>
		<?php
	} else {
		/**
		 * Show Account Info & Disconnect
		 */
		?>
		<div id="edd-paypal-commerce-connect-wrap" class="edd-paypal-connect-account-info notice inline loading" data-nonce="<?php echo esc_attr( wp_create_nonce( 'edd_paypal_account_information' ) ); ?>">
			<ul class="edd-paypal-account-status">
				<li>
					<span></span>
				</li>
				<li>
					<span></span>
				</li>
				<li>
					<span></span>
						<ul class="edd-paypal-webhook-events">
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
						</ul>
				</li>
				<li>
					<span></span>
				</li>
			</ul>
			<p class="edd-paypal-connect-actions">
				<span></span>
				<span></span>
			</p>
		</div>
		<div id="edd-paypal-disconnect"></div>
		<?php
	}
	?>

	<?php
}
add_action( 'edd_paypal_connect_button', __NAMESPACE__ . '\connect_settings_field' );

/**
 * Single function to make a request to get the onboarding URL and nonce.
 *
 * Previously we did this in process_connect method, but we've moved away from the AJAX useage of this
 * in favor of doing it on loading the settings field, to make loading the modal more reliable and faster.
 *
 * @since 3.1.2
 */
function get_onboarding_data() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'code' => 403,
			'body' => array(
				'message' => __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ),
			),
		);
	}

	$mode = edd_is_test_mode() ? API::MODE_SANDBOX : API::MODE_LIVE;

	$existing_connect_details = get_partner_details( $mode );

	if ( ! empty( $existing_connect_details ) ) {
		// Ensure the data we have contains all necessary details.
		if (
			( ! empty( $existing_connect_details->expires ) && $existing_connect_details->expires > time() ) &&
			! empty( $existing_connect_details->nonce ) &&
			! empty( $existing_connect_details->signupLink ) && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			! empty( $existing_connect_details->product )
		) {
			return array(
				'code' => 200,
				'body' => $existing_connect_details,
			);
		}
	}

	$request = new \EDD\Utils\RemoteRequest(
		EDD_PAYPAL_PARTNER_CONNECT_URL . 'signup-link',
		array(
			'headers'    => array(
				'Content-Type' => 'application/json',
			),
			'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
			'body'       => wp_json_encode(
				array(
					'mode'          => $mode,
					'country_code'  => edd_get_shop_country(),
					'currency_code' => edd_get_currency(),
					'return_url'    => get_settings_url(),
				)
			),
			'method'     => 'POST',
		)
	);

	if ( is_wp_error( $request->response ) ) {

		return array(
			'code' => $request->code,
			'body' => $request->response->get_error_message(),
		);
	}

	$body = json_decode( $request->body );

	// We're storing an expiration so we can get a new one if it's been a day.
	$body->expires = time() + DAY_IN_SECONDS;

	// We need to store this temporarily so we can use the nonce again in the next request.
	update_option( 'edd_paypal_commerce_connect_details_' . $mode, wp_json_encode( $body ), false );

	return array(
		'code' => $request->code,
		'body' => $body,
	);
}

/**
 * AJAX handler for processing the PayPal Connection.
 *
 * @since 2.11
 * @deprecated 3.1.2 Instead of doing this via an AJAX request, we now do this on page load.
 *
 * @return void
 */
function process_connect() {
	_edd_deprecated_function( __FUNCTION__, '3.1.2', 'EDD_PayPal_Commerce::get_onboarding_data()' );

	// This validates the nonce.
	check_ajax_referer( 'edd_process_paypal_connect' );

	$onboarding_data = get_onboarding_data();

	if ( 200 !== intval( $onboarding_data['code'] ) ) {
		wp_send_json_error(
			sprintf(
			/* translators: 1: HTTP response code, 2: error message */
				__( 'Unexpected response code: %1$d. Error: %2$s', 'easy-digital-downloads' ),
				$onboarding_data['code'],
				wp_json_encode( $onboarding_data['body'] )
			)
		);
	}

	if ( empty( $onboarding_data['body']->signupLink ) || empty( $onboarding_data['body']->nonce ) ) {
		wp_send_json_error( __( 'An unexpected error occurred.', 'easy-digital-downloads' ) );
	}

	wp_send_json_success( $onboarding_data['body'] );
}

/**
 * AJAX handler for processing the PayPal Reconnect.
 *
 * @since 3.1.0.3
 * @return void
 */
function process_reconnect() {
	// This validates the nonce.
	check_ajax_referer( 'edd_process_paypal_connect' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	$mode = edd_is_test_mode() ? API::MODE_SANDBOX : API::MODE_LIVE;

	/**
	 * Make sure we still have connection details from the previously connected site.
	 */
	$connection_details = get_option( 'edd_paypal_commerce_connect_details_' . $mode );

	if ( empty( $connection_details ) ) {
		// Somehow we ended up here, but now that we're in an invalid state, remove all settings so we can fully reset.
		delete_option( 'edd_paypal_commerce_connect_details_' . $mode );
		delete_option( 'edd_paypal_commerce_webhook_id_' . $mode );
		delete_option( 'edd_paypal_' . $mode . '_merchant_details' );
		wp_send_json_error( __( 'Failure reconnecting to PayPal. Please try again', 'easy-digital-downloads' ) );
	}

	try {
		PayPal\Webhooks\create_webhook( $mode );
	} catch ( \Exception $e ) {
		$message = esc_html__( 'Your account has been successfully reconnected, but an error occurred while creating a webhook.', 'easy-digital-downloads' );
	}

	wp_safe_redirect( esc_url_raw( get_settings_url() ) );
}
add_action( 'wp_ajax_edd_paypal_commerce_reconnect', __NAMESPACE__ . '\process_reconnect' );

/**
 * Retrieves partner Connect details for the given mode.
 *
 * @param string $mode Store mode. If omitted, current mode is used.
 *
 * @return stdObj|null
 */
function get_partner_details( $mode = '' ) {
	if ( ! $mode ) {
		$mode = edd_is_test_mode() ? API::MODE_SANDBOX : API::MODE_LIVE;
	}
	return json_decode( get_option( 'edd_paypal_commerce_connect_details_' . $mode ) );
}

/**
 * AJAX handler for retrieving a one-time access token, then used to retrieve
 * the seller's API credentials.
 *
 * @since 2.11
 * @return void
 */
function get_and_save_credentials() {
	// This validates the nonce.
	check_ajax_referer( 'edd_process_paypal_connect' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	if ( empty( $_POST['auth_code'] ) || empty( $_POST['share_id'] ) ) {
		wp_send_json_error( __( 'Missing PayPal authentication information. Please try again.', 'easy-digital-downloads' ) );
	}

	$mode = edd_is_test_mode() ? PayPal\API::MODE_SANDBOX : PayPal\API::MODE_LIVE;

	// Store a transient to indicate that we've started the connect process.
	set_transient(
		'edd_paypal_commerce_connect_started_' . $mode,
		wp_hash( get_current_user_id() . '_' . $mode . '_started', 'nonce' ),
		15 * MINUTE_IN_SECONDS
	);

	$partner_details = get_partner_details( $mode );
	if ( empty( $partner_details->nonce ) ) {
		wp_send_json_error( __( 'Missing nonce. Please refresh the page and try again.', 'easy-digital-downloads' ) );
	}

	$paypal_subdomain = edd_is_test_mode() ? '.sandbox' : '';
	$api_url          = 'https://api-m' . $paypal_subdomain . '.paypal.com/';
	$api_args         = array(
		'headers'    => array(
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Authorization' => sprintf( 'Basic %s', base64_encode( $_POST['share_id'] ) ),
			'timeout'       => 15,
		),
		'body'       => array(
			'grant_type'    => 'authorization_code',
			'code'          => $_POST['auth_code'],
			'code_verifier' => $partner_details->nonce,
		),
		'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
		'method'     => 'POST',
	);

	/*
	 * First get a temporary access token from PayPal.
	 */
	$request = new \EDD\Utils\RemoteRequest(
		$api_url . 'v1/oauth2/token',
		$api_args
	);

	if ( is_wp_error( $request->response ) ) {
		wp_send_json_error( $request->response->get_error_message() );
	}

	$body = json_decode( $request->body );

	if ( empty( $body->access_token ) ) {
		wp_send_json_error(
			sprintf(
				/* translators: %d: HTTP response code */
				__( 'Unexpected response from PayPal while generating token. Response code: %d. Please try again.', 'easy-digital-downloads' ),
				$request->code
			)
		);
	}

	/*
	 * Now we can use this access token to fetch the seller's credentials for all future
	 * API requests.
	 */
	$request = new \EDD\Utils\RemoteRequest(
		$api_url . 'v1/customer/partners/' . urlencode( \EDD\Gateways\PayPal\get_partner_merchant_id( $mode ) ) . '/merchant-integrations/credentials/',
		array(
			'headers'    => array(
				'Authorization' => sprintf( 'Bearer %s', $body->access_token ),
				'Content-Type'  => 'application/json',
				'timeout'       => 15,
			),
			'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
		)
	);

	if ( is_wp_error( $request->response ) ) {
		wp_send_json_error( $request->response->get_error_message() );
	}

	$code = $request->code;
	$body = json_decode( $request->body );

	if ( empty( $body->client_id ) || empty( $body->client_secret ) ) {
		wp_send_json_error(
			sprintf(
			/* translators: %d: HTTP response code */
				__( 'Unexpected response from PayPal. Response code: %d. Please try again.', 'easy-digital-downloads' ),
				$code
			)
		);
	}

	edd_update_option( 'paypal_' . $mode . '_client_id', sanitize_text_field( $body->client_id ) );
	edd_update_option( 'paypal_' . $mode . '_client_secret', sanitize_text_field( $body->client_secret ) );

	$message = esc_html__( 'Successfully connected.', 'easy-digital-downloads' );

	try {
		PayPal\Webhooks\create_webhook( $mode );
	} catch ( \Exception $e ) {
		$message = esc_html__( 'Your account has been successfully connected, but an error occurred while creating a webhook.', 'easy-digital-downloads' );
	}

	/**
	 * Triggers when an account is successfully connected to PayPal.
	 *
	 * @param string $mode The mode that the account was connected in. Either `sandbox` or `live`.
	 *
	 * @since 2.11
	 */
	do_action( 'edd_paypal_commerce_connected', $mode );

	wp_send_json_success( $message );
}

add_action( 'wp_ajax_edd_paypal_commerce_get_access_token', __NAMESPACE__ . '\get_and_save_credentials' );

/**
 * Verifies the connected account.
 *
 * @since 2.11
 * @return void
 */
function get_account_info() {
	check_ajax_referer( 'edd_paypal_account_information' );

	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_send_json_error( wpautop( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) ) );
	}

	try {
		$status         = 'success';
		$account_status = '';
		$actions        = array(
			'refresh_merchant' => '<button type="button" class="button edd-paypal-connect-action" data-nonce="' . esc_attr( wp_create_nonce( 'edd_check_merchant_status' ) ) . '" data-action="edd_paypal_commerce_check_merchant_status">' . esc_html__( 'Re-Check Payment Status', 'easy-digital-downloads' ) . '</button>',
			'webhook'          => '<button type="button" class="button edd-paypal-connect-action" data-nonce="' . esc_attr( wp_create_nonce( 'edd_update_paypal_webhook' ) ) . '" data-action="edd_paypal_commerce_update_webhook">' . esc_html__( 'Sync Webhook', 'easy-digital-downloads' ) . '</button>',
		);

		$disconnect_links = array(
			'disconnect' => '<a class="button-secondary" id="edd-paypal-disconnect-link" href="' . esc_url( get_disconnect_url() ) . '">' . __( 'Disconnect webhooks from PayPal', 'easy-digital-downloads' ) . '</a>',
			'delete'     => '<a class="button-secondary" id="edd-paypal-delete-link" href="' . esc_url( get_delete_url() ) . '">' . __( 'Delete connection with PayPal', 'easy-digital-downloads' ) . '</a>',
		);

		$validator = new AccountStatusValidator();
		$validator->check();

		/*
		 * 1. Check REST API credentials
		 */
		$rest_api_message = '<strong>' . __( 'API:', 'easy-digital-downloads' ) . '</strong>' . ' ';
		if ( $validator->errors_for_credentials->errors ) {
			$rest_api_dashicon = 'no';
			$status            = 'error';
			$rest_api_message .= $validator->errors_for_credentials->get_error_message();
		} else {
			$rest_api_dashicon = 'yes';
			$mode_string       = edd_is_test_mode() ? __( 'sandbox', 'easy-digital-downloads' ) : __( 'live', 'easy-digital-downloads' );

			/* translators: %s: the connected mode, either `sandbox` or `live` */
			$rest_api_message .= sprintf( __( 'Your PayPal account is successfully connected in %s mode.', 'easy-digital-downloads' ), $mode_string );
		}

		ob_start();
		?>
		<li>
			<span class="dashicons dashicons-<?php echo esc_attr( $rest_api_dashicon ); ?>"></span>
			<span><?php echo wp_kses( $rest_api_message, array( 'strong' => array() ) ); ?></span>
		</li>
		<?php
		$account_status .= ob_get_clean();

		/*
		 * 2. Check merchant account
		 */
		$merchant_account_message = '<strong>' . __( 'Payment Status:', 'easy-digital-downloads' ) . '</strong>' . ' ';
		if ( $validator->errors_for_merchant_account->errors ) {
			$merchant_dashicon         = 'no';
			$status                    = 'error';
			$merchant_account_message .= __( 'You need to address the following issues before you can start receiving payments:', 'easy-digital-downloads' );

			// We can only refresh the status if we have a merchant ID.
			if ( in_array( 'missing_merchant_details', $validator->errors_for_merchant_account->get_error_codes(), true ) ) {
				unset( $actions['refresh_merchant'] );
			}
		} else {
			$merchant_dashicon         = 'yes';
			$merchant_account_message .= __( 'Ready to accept payments.', 'easy-digital-downloads' );
		}

		ob_start();
		?>
		<li>
			<span class="dashicons dashicons-<?php echo esc_attr( $merchant_dashicon ); ?>"></span>
			<span><?php echo wp_kses_post( $merchant_account_message ); ?></span>
			<?php if ( $validator->errors_for_merchant_account->errors ) : ?>
				<ul>
					<?php foreach ( $validator->errors_for_merchant_account->get_error_codes() as $code ) : ?>
						<li><?php echo wp_kses( $validator->errors_for_merchant_account->get_error_message( $code ), array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</li>
		<?php
		$account_status .= ob_get_clean();

		/*
		 * 3. Webhooks
		 */
		$webhook_message = '<strong>' . __( 'Webhook:', 'easy-digital-downloads' ) . '</strong>' . ' ';
		if ( $validator->errors_for_webhook->errors ) {
			$webhook_dashicon = 'no';
			$status           = ( 'success' === $status ) ? 'warning' : $status;
			$webhook_message .= $validator->errors_for_webhook->get_error_message();

			if ( in_array( 'webhook_missing', $validator->errors_for_webhook->get_error_codes(), true ) ) {
				unset( $disconnect_links['disconnect'] );
				$actions['webhook'] = '<button type="button" class="button edd-paypal-connect-action" data-nonce="' . esc_attr( wp_create_nonce( 'edd_create_paypal_webhook' ) ) . '" data-action="edd_paypal_commerce_create_webhook">' . esc_html__( 'Create Webhooks', 'easy-digital-downloads' ) . '</button>';
			}
		} else {
			unset( $disconnect_links['delete'] );
			$webhook_dashicon = 'yes';
			$webhook_message .= __( 'Webhook successfully configured for the following events:', 'easy-digital-downloads' );
		}

		ob_start();
		?>
		<li>
			<span class="dashicons dashicons-<?php echo esc_attr( $webhook_dashicon ); ?>"></span>
			<span><?php echo wp_kses( $webhook_message, array( 'strong' => array() ) ); ?></span>
			<?php if ( $validator->webhook ) : ?>
				<ul class="edd-paypal-webhook-events">
					<?php foreach ( array_keys( PayPal\Webhooks\get_webhook_events() ) as $event_name ) : ?>
						<li>
							<span class="dashicons dashicons-<?php echo in_array( $event_name, $validator->enabled_webhook_events, true ) ? 'yes' : 'no'; ?>"></span>
							<span><?php echo esc_html( $event_name ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</li>
		<?php
		$account_status .= ob_get_clean();

		if ( ! edd_is_gateway_active( 'paypal_commerce' ) ) {
			$account_status .= sprintf(
				/* translators: %1$s opening anchor tag; %2$s closing anchor tag; %3$s: opening line item/status/strong tags; %4$s closing strong tag; %5$s: closing list item tag */
				__( '%3$sGateway Status: %4$s PayPal is not currently active. %1$sEnable PayPal%2$s in the general gateway settings to start using it.%5$s', 'easy-digital-downloads' ),
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=main' ) ) . '">',
				'</a>',
				'<li><span class="dashicons dashicons-no"></span><strong>',
				'</strong>',
				'</li>'
			);
		}

		wp_send_json_success(
			array(
				'status'           => $status,
				'account_status'   => '<ul class="edd-paypal-account-status">' . $account_status . '</ul>',
				'webhook_object'   => isset( $validator ) ? $validator->webhook : null,
				'actions'          => array_values( $actions ),
				'disconnect_links' => array_values( $disconnect_links ),
			)
		);
	} catch ( \Exception $e ) {
		wp_send_json_error(
			array(
				'status'  => isset( $status ) ? $status : 'error',
				'message' => wpautop( $e->getMessage() ),
			)
		);
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_get_account_info', __NAMESPACE__ . '\get_account_info' );

/**
 * Returns the URL for disconnecting from PayPal Commerce.
 *
 * @since 2.11
 * @return string
 */
function get_disconnect_url() {
	return wp_nonce_url(
		add_query_arg(
			array(
				'edd_action' => 'disconnect_paypal_commerce',
			),
			admin_url()
		),
		'edd_disconnect_paypal_commerce'
	);
}

/**
 * Returns the URL for deleting the app PayPal Commerce.
 *
 * @since 3.1.0.3
 * @return string
 */
function get_delete_url() {
	return wp_nonce_url(
		add_query_arg(
			array(
				'edd_action' => 'delete_paypal_commerce',
			),
			admin_url()
		),
		'edd_delete_paypal_commerce'
	);
}

/**
 * Disconnects from PayPal in the current mode.
 *
 * @since 2.11
 * @return void
 */
function process_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_disconnect_paypal_commerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	$mode = edd_is_test_mode() ? PayPal\API::MODE_SANDBOX : PayPal\API::MODE_LIVE;

	try {
		$api = new PayPal\API();

		try {
			// Disconnect the webhook.
			// This is in another try/catch because we want to delete the token cache (below) even if this fails.
			// This only deletes the webhooks in PayPal, we do not remove the webhook ID in EDD until we delete the connection.
			PayPal\Webhooks\delete_webhook( $mode );
		} catch ( \Exception $e ) {
			// We don't want to stop the process if we can't delete the webhooks.
		}

		// Also delete the token cache key, to ensure we fetch a fresh one if they connect to a different account later.
		delete_option( $api->token_cache_key );
	} catch ( \Exception $e ) {
		// We don't want to stop the process if we can't delete the webhook.
	}

	wp_safe_redirect( esc_url_raw( get_settings_url() ) );
	exit;
}
add_action( 'edd_disconnect_paypal_commerce', __NAMESPACE__ . '\process_disconnect' );

/**
 * Fully deletes past Merchant Information from PayPal in the current mode.
 *
 * @since 3.1.0.3
 * @return void
 */
function process_delete() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_delete_paypal_commerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	$mode = edd_is_test_mode() ? PayPal\API::MODE_SANDBOX : PayPal\API::MODE_LIVE;

	// Delete merchant information.
	delete_option( 'edd_paypal_' . $mode . '_merchant_details' );

	// Delete partner connect information.
	delete_option( 'edd_paypal_commerce_connect_details_' . $mode );

	try {
		$api = new PayPal\API();

		try {
			// Disconnect the webhook.
			// This is in another try/catch because we want to delete the token cache (below) even if this fails.
			// This only deletes the webhooks in PayPal, we do not remove the webhook ID in EDD until we delete the connection.
			PayPal\Webhooks\delete_webhook( $mode );
		} catch ( \Exception $e ) {
			// We don't want to stop the process if we can't delete the webhooks.
		}

		// Also delete the token cache key, to ensure we fetch a fresh one if they connect to a different account later.
		delete_option( $api->token_cache_key );
	} catch ( \Exception $e ) {
		// We don't want to stop the process if we can't delete the webhooks.
	}

	// Now delete our webhook ID.
	delete_option( sanitize_key( 'edd_paypal_commerce_webhook_id_' . $mode ) );

	// Delete API credentials.
	$edd_settings_to_delete = array(
		'paypal_' . $mode . '_client_id',
		'paypal_' . $mode . '_client_secret',
	);

	foreach ( $edd_settings_to_delete as $option_name ) {
		edd_delete_option( $option_name );
	}

	// Unset the PayPal Commerce gateway as an enabled gateway.
	$enabled_gateways = edd_get_option( 'gateways', array() );
	unset( $enabled_gateways['paypal_commerce'] );
	edd_update_option( 'gateways', $enabled_gateways );

	wp_safe_redirect( esc_url_raw( get_settings_url() ) );
	exit;
}
add_action( 'edd_delete_paypal_commerce', __NAMESPACE__ . '\process_delete' );

/**
 * AJAX callback for refreshing payment status.
 *
 * @since 2.11
 * @throws \Exception If the merchant ID is not found.
 */
function refresh_merchant_status() {
	check_ajax_referer( 'edd_check_merchant_status' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	$merchant_details = PayPal\MerchantAccount::retrieve();

	try {
		if ( empty( $merchant_details->merchant_id ) ) {
			throw new \Exception( __( 'No merchant ID saved. Please reconnect to PayPal.', 'easy-digital-downloads' ) );
		}

		$partner_details = get_partner_details();
		$nonce           = isset( $partner_details->nonce ) ? $partner_details->nonce : null;

		$new_details      = get_merchant_status( $merchant_details->merchant_id, $nonce );
		$merchant_account = new PayPal\MerchantAccount( $new_details );
		$merchant_account->save();

		wp_send_json_success();
	} catch ( \Exception $e ) {
		wp_send_json_error( esc_html( $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_check_merchant_status', __NAMESPACE__ . '\refresh_merchant_status' );

/**
 * AJAX callback for creating a webhook.
 *
 * @since 2.11
 */
function create_webhook() {
	check_ajax_referer( 'edd_create_paypal_webhook' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	try {
		PayPal\Webhooks\create_webhook();

		wp_send_json_success();
	} catch ( \Exception $e ) {
		wp_send_json_error( esc_html( $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_create_webhook', __NAMESPACE__ . '\create_webhook' );

/**
 * AJAX callback for syncing a webhook. This is used to fix issues with missing events.
 *
 * @since 2.11
 */
function update_webhook() {
	check_ajax_referer( 'edd_update_paypal_webhook' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	try {
		PayPal\Webhooks\sync_webhook();

		wp_send_json_success();
	} catch ( \Exception $e ) {
		wp_send_json_error( esc_html( $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_update_webhook', __NAMESPACE__ . '\update_webhook' );

/**
 * PayPal Redirect Callback
 *
 * This processes after the merchant is redirected from PayPal. We immediately
 * check their seller status via partner connect and save their merchant status.
 * The user is then redirected back to the settings page.
 *
 * @since 2.11
 */
add_action(
	'load-download_page_edd-settings',
	function () {
		if ( ! isset( $_GET['merchantIdInPayPal'] ) || ! edd_is_admin_page( 'settings' ) ) {
			return;
		}

		$mode            = edd_is_test_mode() ? 'sandbox' : 'live';
		$connect_process = get_transient( 'edd_paypal_commerce_connect_started_' . $mode );
		if ( empty( $connect_process ) ) {
			return;
		}
		$check = wp_hash( get_current_user_id() . '_' . $mode . '_started', 'nonce' );

		if ( ! hash_equals( $connect_process, $check ) ) {
			wp_die(
				__( 'There was an error processing the connection to PayPal. Please attempt to connect again.', 'easy-digital-downloads' ),
				__( 'Error', 'easy-digital-downloads' ),
				array(
					'response'  => 403,
					'link_text' => __( 'Return to settings', 'easy-digital-downloads' ),
					'link_url'  => get_settings_url(),
				)
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ),
				__( 'Error', 'easy-digital-downloads' ),
				array( 'response' => 403 )
			);
		}

		edd_debug_log( 'PayPal Connect - Checking merchant status.' );

		$merchant_id = urldecode( $_GET['merchantIdInPayPal'] );

		try {
			$details = get_merchant_status( $merchant_id );
			edd_debug_log( 'PayPal Connect - Successfully retrieved merchant status.' );
		} catch ( \Exception $e ) {
			/*
			 * This won't be enough to actually validate the merchant status, but we want to ensure
			 * we save the merchant ID no matter what.
			 */
			$details = array(
				'merchant_id' => $merchant_id,
			);

			edd_debug_log( sprintf( 'PayPal Connect - Failed to retrieve merchant status from PayPal. Error: %s', $e->getMessage() ) );
		}

		$merchant_account = new PayPal\MerchantAccount( $details );
		$merchant_account->save();

		// Remove our transient, instead of waiting for it to be removed automatically.
		delete_transient( 'edd_paypal_commerce_connect_started_' . $mode );

		edd_redirect( esc_url_raw( get_settings_url() ) );
	}
);

/**
 * Retrieves the merchant's status in PayPal.
 *
 * @param string $merchant_id The merchant ID to check.
 * @param string $nonce       The nonce to use for the request.
 *
 * @return array
 * @throws PayPal\Exceptions\API_Exception If the request fails.
 */
function get_merchant_status( $merchant_id, $nonce = '' ) {
	$api = new API();

	$response = $api->make_request(
		sprintf(
			'v1/customer/partners/%s/merchant-integrations/%s',
			\EDD\Gateways\PayPal\get_partner_merchant_id(),
			$merchant_id
		),
		array(),
		array(),
		'GET'
	);

	if ( 200 === (int) $api->last_response_code ) {
		return $response;
	}

	$response = (array) $response;

	if ( ! empty( $response['error'] ) ) {
		$error_message = $response['error'];
	} elseif ( ! empty( $response['message'] ) ) {
		$error_message = $response['message'];
	} else {
		$error_message = sprintf(
			'Invalid HTTP response code: %d. Response: %s',
			$api->last_response_code,
			$response
		);
	}

	// If the response code is a string, we'll default to a 403 because the API Exception requires an integer.
	if ( ! is_int( $api->last_response_code ) ) {
		throw new PayPal\Exceptions\API_Exception( $error_message, 403 );
	}

	throw new PayPal\Exceptions\API_Exception( $error_message, $api->last_response_code );
}
