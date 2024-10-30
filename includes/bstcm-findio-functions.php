<?php

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce single product description block
 *
 * @since 1.0.0
 * @param none
 * @return array array with loan details
 */
function bfg_get_woocommerce_loan_offer_details($product) {

    // Gather options
    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

    // Get cart totals and set durationInMonths
    $loanAmount = number_format( preg_replace( '#[^\d,]#', '', $product->regular_price ), 2, '.', false );
    $durationInMonths = $settings['durationInMonths'];

    // Execute API call and fetch results
    $apiOfferArray = bfg_api_call(
        $settings['apiurl'].'calculate',
        array(
            'loanAmount' => $loanAmount,
            'durationInMonths' => $durationInMonths,
        )
    );

    // Output results to Ajax function
    return $apiOfferArray;

}
