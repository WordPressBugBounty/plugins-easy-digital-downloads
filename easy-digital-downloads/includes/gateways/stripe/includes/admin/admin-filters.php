<?php

/**
 * Given a Payment ID, extract the transaction ID from Stripe
 *
 * @param  string $payment_id       Payment ID
 * @return string                   Transaction ID
 */
function edds_get_payment_transaction_id( $payment_id ) {

	$txn_id = '';
	$notes  = edd_get_payment_notes( $payment_id );

	foreach ( $notes as $note ) {
		if ( preg_match( '/^Stripe Charge ID: ([^\s]+)/', $note->comment_content, $match ) ) {
			$txn_id = $match[1];
			continue;
		}
	}

	return apply_filters( 'edds_set_payment_transaction_id', $txn_id, $payment_id );
}
add_filter( 'edd_get_payment_transaction_id-stripe', 'edds_get_payment_transaction_id', 10, 1 );

/**
 * Add a link to the dispute ID in the payment details
 *
 * @since  3.2.0
 * @param  string $dispute_id The dispute ID.
 * @param  object $order      The order object.
 * @return string             The HTML markup to link to the dispute
 */
function edds_link_dispute_id( $dispute_id, $order ) {
	$test = 'test' === $order->mode ? 'test/' : '';

	// If the dispute_id starts with d, then it's a dispute.
	if ( 0 === strpos( $dispute_id, 'd' ) ) {
		return sprintf(
			'<a href="https://dashboard.stripe.com/%1$sdisputes/%2$s" target="_blank">%2$s</a>',
			$test,
			$dispute_id
		);
	}

	$stripe_payment = edd_get_order_meta( $order->id, '_edds_stripe_payment_intent_id', true );
	if ( ! $stripe_payment ) {
		return $dispute_id;
	}

	return sprintf(
		'<a href="https://dashboard.stripe.com/%1$spayments/%2$s" target="_blank">%2$s</a>',
		$test,
		$stripe_payment
	);
}
add_filter( 'edd_payment_details_dispute_id_stripe', 'edds_link_dispute_id', 10, 2 );

/**
 * Update the order hold reasons with Stripe-specific reasons.
 *
 * @since  3.2.0
 * @param  array $reasons The reasons for holding an order
 * @return array          The updated reasons
 */
function edds_order_hold_reasons( $reasons ) {
	$stripe_reasons = array(
		'bank_cannot_process'       => __( 'Bank cannot process', 'easy-digital-downloads' ),
		'check_returned'            => __( 'Check returned', 'easy-digital-downloads' ),
		'credit_not_processed'      => __( 'Credit not processed', 'easy-digital-downloads' ),
		'customer_initiated'        => __( 'Customer initiated', 'easy-digital-downloads' ),
		'debit_not_authorized'      => __( 'Debit not authorized', 'easy-digital-downloads' ),
		'duplicate'                 => __( 'Duplicate', 'easy-digital-downloads' ),
		'fraudulent'                => __( 'Fraudulent', 'easy-digital-downloads' ),
		'general'                   => __( 'General', 'easy-digital-downloads' ),
		'incorrect_account_details' => __( 'Incorrect account details', 'easy-digital-downloads' ),
		'insufficient_funds'        => __( 'Insufficient funds', 'easy-digital-downloads' ),
		'product_not_received'      => __( 'Product not received', 'easy-digital-downloads' ),
		'product_unacceptable'      => __( 'Product unacceptable', 'easy-digital-downloads' ),
		'subscription_canceled'     => __( 'Subscription canceled', 'easy-digital-downloads' ),
		'unrecognized'              => __( 'Unrecognized', 'easy-digital-downloads' ),
	);

	return array_merge( $reasons, $stripe_reasons );
}
add_filter( 'edd_order_hold_reasons', 'edds_order_hold_reasons' );
