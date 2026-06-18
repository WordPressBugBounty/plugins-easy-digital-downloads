<?php
/**
 * Gets the PayPal Commerce data for the Site Health report.
 *
 * @package     EDD\Admin\SiteHealth
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Admin\SiteHealth;

/**
 * Loads PayPal Commerce data into Site Health.
 *
 * @since 3.6.9
 */
class PayPalCommerce {

	/**
	 * Gets the PayPal Commerce data array.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	public function get() {
		return array(
			'label'  => __( 'Easy Digital Downloads &mdash; PayPal Commerce', 'easy-digital-downloads' ),
			'fields' => $this->get_fields(),
		);
	}

	/**
	 * Builds the PayPal Commerce fields.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	private function get_fields() {
		$fields = array();

		foreach ( array( 'live', 'sandbox' ) as $mode ) {
			$fields[ "connection_{$mode}" ] = array(
				'label' => sprintf(
					/* translators: %s: PayPal Commerce mode label, either "Live" or "Sandbox". */
					__( 'Connection (%s)', 'easy-digital-downloads' ),
					ucfirst( $mode )
				),
				'value' => $this->get_connection_value( $mode ),
			);
		}

		$fields['payment_methods'] = array(
			'label' => __( 'Enabled Payment Methods', 'easy-digital-downloads' ),
			'value' => $this->get_enabled_payment_methods(),
		);

		return $fields;
	}

	/**
	 * Returns the connection status string for a mode.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode PayPal mode (live or sandbox).
	 * @return string
	 */
	private function get_connection_value( string $mode ): string {
		$merchant_id = get_option( "edd_paypal_{$mode}_merchant_id", '' );

		if ( empty( $merchant_id ) ) {
			return 'Not Connected';
		}

		$version = get_option( "edd_paypal_{$mode}_commerce_version", 'v3' );

		return sprintf( 'Connected (%s)', strtoupper( $version ) );
	}

	/**
	 * Returns a comma-separated list of enabled PayPal payment methods.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function get_enabled_payment_methods(): string {
		$labels = array(
			'paypal'     => 'PayPal',
			'card'       => 'Debit / Credit Card',
			'pay_later'  => 'Pay Later',
			'venmo'      => 'Venmo',
			'apple_pay'  => 'Apple Pay',
			'google_pay' => 'Google Pay',
			'fastlane'   => 'Fastlane',
		);

		$saved = edd_get_option( 'paypal_payment_methods', null );
		if ( ! is_array( $saved ) ) {
			return 'Defaults';
		}

		$enabled = array();
		foreach ( $labels as $slug => $label ) {
			if ( ! empty( $saved[ $slug ] ) ) {
				$enabled[] = $label;
			}
		}

		return empty( $enabled ) ? 'None' : implode( ', ', $enabled );
	}
}
