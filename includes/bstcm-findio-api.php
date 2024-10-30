<?php

defined( 'ABSPATH' ) or exit;

/**
 * Payment gateway API connection method
 *
 * @since 1.0.0
 * @param string $apiEndpoint string $apiArguments
 * @param array $apiArguments
 * @return array array with API response
 */

function bfg_api_call( $apiEndpoint, $apiArguments ) {

    // Fetch settings from gateway
    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

    // Build api parameters and generate transient key based on body arguments
    $apiBody            = json_encode( $apiArguments );
    $apiTransientKey    = hash( 'ripemd160', implode( $apiArguments ) );
    $apiTransientKey	= rand(1000,9999);
    $apiAccount         = $settings['apiusername'];
    $apiKey             = $settings['apikey'];
    $apiAuth            = "ApiKey {$apiAccount}:{$apiKey}";

    // Get API response from transient or execute wp_remote_post request
    //if ( false === ( $apiResponse = get_transient( $apiTransientKey ) ) ) {

        // Build arguments for live API call
        $apiArguments = array(
            'method' => 'POST',
            'timeout' => '45',
            'redirection' => '10',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $apiAuth,
            ),
            'body' => $apiBody,
            'cookies' => array()
        );

        // Execute new API call for response
        $apiResponse = wp_remote_post( $apiEndpoint, $apiArguments );

        // Store API response inside transient
        //set_transient( $apiTransientKey, $apiResponse, 1 * HOUR_IN_SECONDS );

    //}

    // Load template HTML
    $template = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-checkout.html');

    // If error occured (status code is not 200)
    if ( 200 != $apiResponse['response']['code'] ) {

        return array(
            'status' => $apiResponse['response']['code'],
            'response' => $apiResponse['response']['message'],
            'message' => $apiResponse->get_error_message(),
            'endpoint' => $apiEndpoint,
            'arguments' => $apiArguments,
        );

    // If response is succesfull (status code is 200)
    } else {

        // Decode response into an object
        $apiResult = json_decode( wp_remote_retrieve_body( $apiResponse ) );

        // Replace template HTML values with results
        $template = str_replace(
            array(
                '{{findiologo}}',
                '{{findiobanner}}',
                '{{totalCosts}}',
                '{{durationInMonths}}',
                '{{nominalAnnualRate}}',
                '{{termPayment}}',
                '{{loanAmount}}',
            ),
            array(
                BFG_PLUGIN_URL.'assets/img/findio-logo.svg', // findiologo
                BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.png', // findiobanner
                number_format_i18n( (float)$apiResult->totalCosts, 2 ), // totalCosts
                (int)$apiResult->durationInMonths, // durationInMonths
                (float)$apiResult->nominalAnnualRate, // nominalAnnualRate
                number_format_i18n( (float)$apiResult->termPayment, 2 ), // termPayment
                number_format_i18n( (float)$apiResult->loanAmount, 2 ), // loanAmount
            ),
            $template
        );

        // Return results array back to caller function
        return array(
            'status' => $apiResponse['response']['code'],
            'html' => $template,
            'endpoint' => $apiEndpoint,
            'arguments' => $apiArguments,
            'totalCosts' => (float)$apiResult->totalCosts,
            'durationInMonths' => (int)$apiResult->durationInMonths,
            'effectiveAnnualRate' => (float)$apiResult->effectiveAnnualRate,
            'termPayment' => (float)$apiResult->termPayment
        );

    }

}
