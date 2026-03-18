<?php
/**
 * Shared validation utilities for Stripe checkout flows.
 *
 * @package EDD\Gateways\Stripe\Checkout
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.6
 */

namespace EDD\Gateways\Stripe\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Validation class.
 *
 * Consolidates common Stripe checkout validation checks.
 *
 * @since 3.6.6
 */
class Validation {

	/**
	 * Validate that an intent has an acceptable status.
	 *
	 * @since 3.6.6
	 *
	 * @param \Stripe\PaymentIntent|\Stripe\SetupIntent $intent         The Stripe Intent object.
	 * @param array                                     $valid_statuses Acceptable statuses.
	 * @return void
	 * @throws \EDD_Stripe_Gateway_Exception If the intent status is invalid.
	 */
	public static function intent_status( $intent, $valid_statuses = array( 'succeeded', 'requires_capture' ) ) {
		if ( in_array( $intent->status, $valid_statuses, true ) ) {
			return;
		}

		throw new \EDD_Stripe_Gateway_Exception(
			esc_html__(
				'An error occurred, but your payment may have gone through. Please contact the site administrator.',
				'easy-digital-downloads'
			),
			'Invalid Intent status ' . $intent->status . ' during payment processing.'
		);
	}

	/**
	 * Validate that the intent amount matches the expected price.
	 *
	 * Skips validation for SetupIntents which have no amount.
	 *
	 * @since 3.6.6
	 *
	 * @param \Stripe\PaymentIntent|\Stripe\SetupIntent $intent         The Stripe Intent object.
	 * @param float|string                              $expected_price The expected price in standard currency units.
	 * @return void
	 * @throws \EDD_Stripe_Gateway_Exception If the amounts do not match.
	 */
	public static function intent_amount( $intent, $expected_price ) {
		if ( isset( $intent->object ) && 'setup_intent' === $intent->object ) {
			return;
		}

		self::verify_amount( (int) $intent->amount, $expected_price, $intent->id );
	}

	/**
	 * Validate that a charge amount matches the expected price.
	 *
	 * @since 3.6.6
	 *
	 * @param \Stripe\Charge $charge         The Stripe Charge object.
	 * @param float|string   $expected_price The expected price in standard currency units.
	 * @return void
	 * @throws \EDD_Stripe_Gateway_Exception If the amounts do not match.
	 */
	public static function charge_amount( $charge, $expected_price ) {
		self::verify_amount( (int) $charge->amount, $expected_price, $charge->id );
	}

	/**
	 * Validate that purchase data is present and non-empty.
	 *
	 * @since 3.6.6
	 *
	 * @param mixed $purchase_data The purchase data to validate.
	 * @return void
	 * @throws \EDD_Stripe_Gateway_Exception If purchase data is empty.
	 */
	public static function purchase_data( $purchase_data ) {
		if ( ! empty( $purchase_data ) ) {
			return;
		}

		throw new \EDD_Stripe_Gateway_Exception(
			esc_html__(
				'An error occurred, but your payment may have gone through. Please contact the site administrator.',
				'easy-digital-downloads'
			),
			'Unable to retrieve purchase data during payment creation.'
		);
	}

	/**
	 * Validate that an intent object or array has an ID.
	 *
	 * @since 3.6.6
	 *
	 * @param mixed $intent The intent data (object or array).
	 * @return void
	 * @throws \EDD_Stripe_Gateway_Exception If the intent has no ID.
	 */
	public static function intent_exists( $intent ) {
		$has_id = is_object( $intent )
			? ! empty( $intent->id )
			: ( is_array( $intent ) && isset( $intent['id'] ) );

		if ( $has_id ) {
			return;
		}

		throw new \EDD_Stripe_Gateway_Exception(
			esc_html__(
				'An error occurred, but your payment may have gone through. Please contact the site administrator.',
				'easy-digital-downloads'
			),
			'Unable to retrieve Intent data during payment processing.'
		);
	}

	/**
	 * Verify that a Stripe amount (in smallest currency unit) matches the expected price.
	 *
	 * @since 3.6.6
	 *
	 * @param int          $actual_amount The amount from Stripe in smallest currency unit (e.g. cents).
	 * @param float|string $expected_price The expected price in standard currency units (e.g. dollars).
	 * @param string       $reference_id   The Stripe object ID for logging.
	 * @return void
	 * @throws \EDD_Stripe_Gateway_Exception If the amounts do not match.
	 */
	private static function verify_amount( $actual_amount, $expected_price, $reference_id ) {
		if ( edds_is_zero_decimal_currency() ) {
			$expected = round( (float) $expected_price, 0 );
		} else {
			$expected = round( (float) $expected_price * 100, 0 );
		}

		if ( abs( $actual_amount - $expected ) <= 1 ) {
			return; // Within rounding tolerance.
		}

		edd_record_gateway_error(
			'Stripe Gateway Error',
			sprintf(
				'Payment amount validation failed. Expected: %d, Received: %d. Reference: %s',
				$expected,
				$actual_amount,
				$reference_id
			)
		);

		throw new \EDD_Stripe_Gateway_Exception(
			esc_html__(
				'An error occurred processing your payment. Please try again or contact support.',
				'easy-digital-downloads'
			),
			sprintf( 'Amount validation failed: expected %d, received %d for %s', $expected, $actual_amount, $reference_id )
		);
	}
}
