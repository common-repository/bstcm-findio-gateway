<?php

defined( 'ABSPATH' ) or exit;

class bfg_WC_Gateway_Findio extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
		bfg_write_log( '__construct()');
		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');

		// Configure gateway
		$this->loanAmount           = 0;
        $this->durationInMonths     = $this->get_option( 'durationInMonths' );
		$this->id                   = 'bfg_gateway';
		$this->icon                 = apply_filters('woocommerce_offline_icon', '');
		$this->has_fields           = false;
		$this->method_title         = __( 'Findio', 'bstcm-findio-gateway' );
		$this->method_description   = $this->get_option( 'method_description' );
		$this->description			= $this->get_option( 'description' );

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->title        		= $this->get_option( 'title' );
        $this->instructions 		= $this->get_option( 'instructions', $this->description );

		if ( is_admin() ) {
			// Init all form fields
			$this->init_form_fields();
		}

		/* HOOKS WERE HERE */

	}

	/**
	 * Initialize Gateway hooks
	 */
	public function init_hooks() {

		// Conditional payment availability
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'bfg_gateway_availability' ), 1 );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Ajax
		add_action( 'wp_ajax_bfg_get_woocommerce_loan_offer_block_hook', array( $this, 'get_offer_block' ) );
		add_action( 'wp_ajax_nopriv_bfg_get_woocommerce_loan_offer_block_hook', array( $this, 'get_offer_block' ) );
		add_action( 'wp_ajax_bfg_get_woocommerce_loan_offer_shortcode_hook', array( $this, 'get_offer_shortcode' ) );
		add_action( 'wp_ajax_nopriv_bfg_get_woocommerce_loan_offer_shortcode_hook', array( $this, 'get_offer_shortcode' ) );
		add_action( 'wp_ajax_bfg_get_woocommerce_single_block_shortcode_hook', array( $this, 'get_single_block' ) );
		add_action( 'wp_ajax_nopriv_bfg_get_woocommerce_single_block_shortcode_hook', array( $this, 'get_single_block' ) );
		add_action( 'wp_ajax_bfg_generate_loan_offer_shortcode_hook', array( $this, 'generate_loan_offer_shortcode' ) );
		add_action( 'wp_ajax_nopriv_bfg_generate_loan_offer_shortcode_hook', array( $this, 'generate_loan_offer_shortcode' ) );
		add_action( 'wp_ajax_bfg_test_api_connection_hook', array( $this, 'bfg_api_test' ) );
		add_action( 'wp_ajax_nopriv_bfg_test_api_connection_hook', array( $this, 'bfg_api_test' ) );
		add_action( 'wp_ajax_bfg_add_to_collaterals_hook', array( $this, 'bfg_add_to_collaterals' ) );
		add_action( 'wp_ajax_nopriv_bfg_add_to_collaterals_hook', array( $this, 'bfg_add_to_collaterals' ) );

		//bstcmfw_write_log("actions generated");

		// Shortcodes
		add_shortcode( 'findio-voorstel', array( $this, 'generate_loan_offer_shortcode' ) );
		add_shortcode( 'findio-totaal', array( $this, 'generate_loan_total_shortcode' ) );
		add_shortcode( 'findio-single', array( $this, 'generate_loan_single_shortcode' ) );
		add_shortcode( 'findio-tabel', array( $this, 'generate_loan_table_shortcode' ) );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		// Callback for Findio
		add_action( 'woocommerce_api_bfg_wc_gateway_bfg_gateway', array( $this, 'bfg_gateway_callback'));

		// Branding hook for product price
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'get_single_block' ), 11 );

		// Cart collateral loan offer block
		add_filter( 'woocommerce_cart_collaterals', array( $this, 'bfg_add_to_collaterals' ), 11 );

		// Financial example table hooks
		add_filter( 'woocommerce_after_single_product', array( $this, 'bfg_add_financial_table' ), 11 );
		add_filter( 'woocommerce_after_checkout_form', array( $this, 'bfg_add_financial_table' ), 11 );
		add_filter( 'woocommerce_after_cart', array( $this, 'bfg_add_financial_table' ), 11 );

	}


	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		// Fetch pages for 'information page field'
		$pagesObject = get_posts(
			array(
				'post_type' => 'page',
				'orderby' => 'title',
				'posts_per_page' => -1
			)
		);

		$pagesOptions = array( '0' => '-- ' . __( 'Choose', 'bstcm-findio-gateway' ) . ' --' );

		foreach ( $pagesObject as $pageObject ) {
			$pagesOptions[ $pageObject->ID ] = $pageObject->post_title;
		}

		$api_status = $this->bfg_api_test();
		//echo "<pre>"; print_r($api_status); echo "</pre>";

		if ( is_wp_error( $api_status ) ) {

			$api_status_text = '<strong style="color:red;">'.__('API service is offline', 'bstcm-findio-gateway' ).'</strong>';

		} elseif ( 400 == $api_status['status']['response']['code'] || 200 == $api_status['status']['response']['code'] ) {

			$api_status_text = '<strong style="color:green;">'.__('API service is online', 'bstcm-findio-gateway' ).'</strong>';

		} else {

			$api_status_text = '<strong style="color:red;">'.__('API service is offline (statuscode: '.$api_status['status']['response']['code'].')', 'bstcm-findio-gateway' ).'</strong>';

		}

		$this->form_fields = apply_filters( 'bfg_form_fields', array(

			'enabled' => array(
				'title'   => __( 'Enable/disable', 'bstcm-findio-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Activate Findio payment method', 'bstcm-findio-gateway' ),
			),

			'api_status_section' => array(
				'title'       => __( 'API status', 'bstcm-findio-gateway' ),
				'type'        => 'title',
				'description' => sprintf( __( 'Connection with API service: %s', 'bstcm-findio-gateway' ), $api_status_text ),
			),

			'api_paymentgatewayoptions_section' => array(
				'title'       => __( 'Gateway descriptions', 'bstcm-findio-gateway' ),
				'type'        => 'title',
				//'description' => __( 'Settings to modify front-end display of Findio items (offer blocks, information pages, example tables).', 'bstcm-findio-gateway' ),
			),

			'title' => array(
				'title'       => __( 'Title', 'bstcm-findio-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment methode title for user', 'bstcm-findio-gateway' ),
				'desc_tip'    => true,
			),

			'description' => array(
				'title'       => __( 'Description', 'bstcm-findio-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description for user', 'bstcm-findio-gateway' ),
				'desc_tip'    => true,
			),

			'instructions' => array(
				'title'       => __( 'Instructions', 'bstcm-findio-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions will be added to the confirmation e-mails and pages.', 'bstcm-findio-gateway' ),
				'desc_tip'    => true,
			),

			'api_information_section' => array(
				'title'       => __( 'Front-end display', 'bstcm-findio-gateway' ),
				'type'        => 'title',
				'description' => __( 'Settings to modify front-end display of Findio items (offer blocks, information pages, example tables).', 'bstcm-findio-gateway' ),
			),

			'information' => array(
                'title'       => __( 'Information page', 'bstcm-findio-gateway' ),
                'type'        => 'select',
                'description' => __( 'Select WordPress page containing additional informatie for payment method.', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
				'options'     => $pagesOptions
            ),

			'showloanoffer' => array(
				'title'   => __( 'Loan offer (product)', 'bstcm-findio-gateway' ),
				'type'    => 'checkbox',
				'value'   => 1,
				'label'   => __( 'Show loan offer on single product page', 'bstcm-findio-gateway' ),
				'description' => __( 'Leave unchecked to enable shortcode usage.<br/>[findio-voorstel]', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
			),

			'showloanofferCart' => array(
				'title'   => __( 'Loan offer (cart)', 'bstcm-findio-gateway' ),
				'type'    => 'checkbox',
				'value'   => 1,
				'label'   => __( 'Show loan offer on cart page', 'bstcm-findio-gateway' ),
				'description' => __( 'Leave unchecked to disable loan offer on cart page.', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
			),

			'showFinancialTable' => array(
				'title'   => __( 'Loan financial table', 'bstcm-findio-gateway' ),
				'type'    => 'checkbox',
				'value'   => 1,
				'label'   => __( 'Show financial table on product, cart and checkout pages', 'bstcm-findio-gateway' ),
				'description' => __( 'Leave unchecked to disable financial table display on pages.', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
			),

			'api_configuration_section' => array(
				'title'       => __( 'API configuration', 'bstcm-findio-gateway' ),
				'type'        => 'title',
				'description' => __( 'Configration for the Findio API calls and calculations.', 'bstcm-findio-gateway' ),
			),

			'loanCategory' => array(
                'title'       => __( 'Products category', 'bstcm-findio-gateway' ),
                'type'        => 'select',
                'description' => __( 'Select global products category used by the API.', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
				'options'     => array(
					'0' => '-- ' . __( 'Choose', 'bstcm-findio-gateway' ) . ' --',
					'Smartphones, navigatiesystemen, portables' => 'Smartphones, navigatiesystemen, portables',
					'Consumentenelektronica, klein huishoudelijk' => 'Consumentenelektronica, klein huishoudelijk',
					'Groot huishoudelijk, witgoed' => 'Groot huishoudelijk, witgoed',
					'Fietsen, scooters, mobiliteit' => 'Fietsen, scooters, mobiliteit',
					'Woningverbetering, verbouwing' => 'Woningverbetering, verbouwing',
				)
            ),

            'durationInMonths' => array(
                'title'       => __( 'Terms', 'bstcm-findio-gateway' ),
                'type'        => 'number',
                'description' => __( 'Number of payment terms', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),

            'minAmount' => array(
                'title'       => __( 'Min. amount', 'bstcm-findio-gateway' ),
                'type'        => 'number',
                'description' => __( 'Minimal amount that is accepted by the API', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),

			'maxAmount' => array(
                'title'       => __( 'Max. amount', 'bstcm-findio-gateway' ),
                'type'        => 'number',
                'description' => __( 'Maximum amount that is accepted by the API', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),

            'apiusername' => array(
                'title'       => __( 'API account', 'bstcm-findio-gateway' ),
                'type'        => 'text',
                'description' => __( 'API account details for Findio', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),

			'apikey' => array(
                'title'       => __( 'API key', 'bstcm-findio-gateway' ),
                'type'        => 'text',
                'description' => __( 'API key for Findio', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),

			'apisecret' => array(
                'title'       => __( 'API secret', 'bstcm-findio-gateway' ),
                'type'        => 'text',
                'description' => __( 'API secret for Findio', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),

			/*
            'apiurl' => array(
                'title'       => __( 'API url', 'bstcm-findio-gateway' ),
                'type'        => 'text',
                'description' => __( 'API URL endpoint for Findio', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
            ),
			*/

			'apiurl' => array(
                'title'       => __( 'API url', 'bstcm-findio-gateway' ),
                'type'        => 'select',
                'description' => __( 'API URL endpoint for Findio.', 'bstcm-findio-gateway' ),
                'desc_tip'    => true,
				'options'     => array(
					'https://closefo-pubapp-prod.creditagricole.davincicloud.nl/webshopservice/api/' => __('Production environment', 'bstcm-findio-gateway'),
					'https://closefo-pubapp-acc.creditagricole.davincicloud.nl/webshopservice/api/' => __('Acceptation environment', 'bstcm-findio-gateway')
				),
            ),

			'api_callback_section' => array(
				'title'       => __( 'API callback URL', 'bstcm-findio-gateway' ),
				'type'        => 'title',
				'description' => get_site_url( null, '/wc-api/bfg_wc_gateway_bfg_gateway/', null ),
			),

		) );
	}


	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}
	}

	/**
	 * Remove Findio payment gateway if total amount is outside limits
	 *
	 * @access public
	 * @param array $gateways
	 * @return array
	 */
	public function bfg_gateway_availability( $gateways ) {

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		$cartAmount = WC()->cart->total;

		if ( $cartAmount > $settings['maxAmount'] || $cartAmount < $settings['minAmount'] ) {
			unset( $gateways['bfg_gateway'] );
		}

		return $gateways;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {

			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;

		}

	}


	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
		bfg_write_log( 'process_payment()' );
		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');

		$loanItems = array();

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Get order
		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'on-hold', __( 'Awaiting loan request approval', 'bstcm-findio-gateway' ) );

		// Define loanAmount (incl. shipping) and terms
		$loanAmount = number_format( WC()->cart->total, 2, '.', false );
	    $durationInMonths = $settings['durationInMonths'];

		// Fetch order items
		$orderItems = $order->get_items();

		foreach ( $orderItems as $orderItem ) {

			// Calculate line total
			$orderItem['line_total_incl_tax'] = number_format( $orderItem['line_total'], 2, '.', false ) + number_format( $orderItem['line_tax'], 2, '.', false );

			// Add product to loanItems
			array_push(
				$loanItems,
				array(
					'itemName' => $orderItem['name'],
					'itemCategory' => $settings['loanCategory'],
					'itemUnitCount' => (int)$orderItem['qty'],
					'itemUnitPrice' => number_format( $orderItem['line_total_incl_tax'], 2, '.', false ),
				)
			);

		}

		// Process shipping cost (if found)
		if ( 0 < WC()->cart->shipping_total ) {

			// Fetch shipping cost
			$orderItem['line_total_incl_tax'] = number_format( WC()->cart->shipping_total, 2, '.', false );

			// Add shipping cost to loanItems
			array_push(
				$loanItems,
				array(
					'itemName' => __( 'Shipping costs', 'bstcm-findio-gateway' ),
					'itemCategory' => $settings['loanCategory'],
					'itemUnitCount' => 1,
					'itemUnitPrice' => $orderItem['line_total_incl_tax'],
				)
			);

		}

		// Define loanRequest parameters
		$loanRequestArgs = array(
			'orderID' => $order_id,
			'loanAmount' => $loanAmount,
			'purchaseAmount' => $loanAmount,
			'orderItems' => $loanItems,
			'returnURL' => $this->get_return_url( $order ),
			'CallBackUrl' => get_site_url( null, '/wc-api/bfg_wc_gateway_bfg_gateway/', null ),
		);

		bfg_write_log( $loanRequestArgs );

		// Execute API call and fetch results
		$apiOfferArray = $this->get_api_response_request(
	        $settings['apiurl'].'loanrequest',
	        $loanRequestArgs
	    );

		$apiRequestUrl = $apiOfferArray['requesturl'];

		// Reduce stock levels
		wc_reduce_stock_levels($order_id);

		// Clear cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $apiRequestUrl
		);

	}

	/**
	 * WooCommerce single product offer block
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string HTML content block (with branding)
	 */
	public function get_single_block() {

		// Use current product data
		global $product;

		bstcmfw_write_log( "PRIJS: " . $product->get_price() );

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Output block if setting is enabled
		if ( 'yes' == $settings['showloanoffer'] ) {

			//echo "<pre>"; print_r( $product->price  ); echo "</pre>";

			// Execute API call and fetch results
			$apiOfferArray = $this->get_api_response_calculate(
				$settings['apiurl'].'calculate',
				array(
					'loanAmount' => $product->get_price(),
					'durationInMonths' => $settings['durationInMonths'],
				)
			);

			if ( $product->get_price() <= $settings['maxAmount'] && $product->get_price() >= $settings['minAmount'] ) {

				echo $apiOfferArray['single'];

			}

		}



	}

	/**
	 * Add findio financial table to page template
	 *
	 * @since 1.0.0
	 * @param array none
	 * @return string HTML block with financial table
	 */
	public function bfg_add_financial_table() {

		// Gather options
		$settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Check if loan offer should be displayed on cart page
		if ( 'yes' == $settings['showFinancialTable'] ) {

			bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
			bfg_write_log( 'bfg_add_financial_table()' );
			bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');

			// Output shortcode
			echo do_shortcode( "[findio-tabel]" );

		}

	}

	/**
	 * Add findio price offer to cart collaterals table
	 *
	 * @since 1.0.0
	 * @param array none
	 * @return string HTML block with offer details
	 */
	public function bfg_add_to_collaterals() {

		// Gather options
		$settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Check if loan offer should be displayed on cart page
		if ( 'yes' == $settings['showloanofferCart'] ) {

			if (defined('DOING_AJAX') && DOING_AJAX) {

				// Return
				return do_shortcode( "[findio-totaal]" );

			} else {

				// Output shortcode
				echo do_shortcode( "[findio-totaal]" );

			}

		}

	}

	/**
	 * WooCommerce checkout get payment method description block
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string jSON encoded array with loan details
	 */
	public function get_offer_block() {

		//bstcmfw_write_log("get_offer_block() running");

	    // Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

	    // Get cart totals (incl. shipping) and set durationInMonths
	    $loanAmount = number_format( WC()->cart->total, 2, '.', false );
	    $durationInMonths = $settings['durationInMonths'];

	    // Execute API call and fetch results
	    $apiOfferArray = $this->get_api_response_calculate(
	        $settings['apiurl'].'calculate',
	        array(
	            'loanAmount' => $loanAmount,
	            'durationInMonths' => $durationInMonths,
	        )
	    );

	    // Output results to Ajax function
	    echo json_encode( $apiOfferArray );

	    // Exit
	    wp_die();

	}

	/**
	 * WooCommerce checkout get payment method shortcode details
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string jSON encoded array with loan details
	 */
	public function get_offer_shortcode() {

		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
		bfg_write_log("get_offer_shortcode()");
		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');

	    // Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

	    // Get cart totals and set durationInMonths
	    $loanAmount = number_format( WC()->cart->subtotal, 2, '.', false );
	    $durationInMonths = $settings['durationInMonths'];

	    // Execute API call and fetch results
	    $apiOfferArray = $this->get_api_response_calculate(
	        $settings['apiurl'].'calculate',
	        array(
	            'loanAmount' => $loanAmount,
	            'durationInMonths' => $durationInMonths,
	        )
	    );

	    if ( 500 == $apiOfferArray['status'] ) {

		    // Output results to Ajax function
		    echo json_encode( $apiOfferArray );

	    } else {

		    // Output results to Ajax function
		    echo json_encode( $apiOfferArray );

	    }

	    // Exit
	    wp_die();

	}

	/**
	 * Findio loan offer Shortcodes
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string HTML formatted blok for loan offer
	 */
	public function generate_loan_offer_shortcode() {

		// Use current product data
		global $product;

		// Set price
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

			$amount = $_POST['variationPrice'];

		} else {

			//$amount = $product->price;
			if ( $product->is_on_sale() ) {
				$amount = $product->get_sale_price();
			} else {
				$amount = $product->get_regular_price();
			}


		}

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Check if amount is within limits

		// Execute API call and fetch results
		$apiOfferArray = $this->get_api_response_calculate(
			$settings['apiurl'].'calculate',
			array(
				'loanAmount' => $amount,
				'durationInMonths' => $settings['durationInMonths'],
			)
		);

		//bstcmfw_write_log( 'generating loan offer block for single product' );

		if ( $amount <= $settings['maxAmount'] && $amount >= $settings['minAmount'] ) { // If amount is within limits

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				echo json_encode(
					array(
						'status' => 200,
						'html' => $apiOfferArray['shortcode'],
						'termPayment' => '&euro;' . number_format_i18n( (float)$apiOfferArray['termPayment'], 2 ),
					)
				);

				wp_die();

			} else {

				// Return template
				return $apiOfferArray['shortcode'];

			}

		} else {

			//bstcmfw_write_log( 'amount is outside limits' );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				echo json_encode(
					array(
						'status' => 300,
						'html' => $apiOfferArray['maxedout'],
						'termPayment' => '&euro;' . number_format_i18n( (float)$apiOfferArray['termPayment'], 2 ),
					)
				);

				wp_die();

			} else {

				// Return template
				return $apiOfferArray['maxedout'];

			}

		}



	}

	/**
	 * Findio loan table Shortcodes
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string HTML formatted comparisment table
	 */
	public function generate_loan_table_shortcode() {

		global $woocommerce, $product;

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Set example amount in case not on cart or single product page
		$amount = 1079;

		// Set amount to cart totals when on cart or checkout page
		if ( ( is_cart() || is_checkout() ) && WC()->cart->total <= $settings['maxAmount'] && WC()->cart->total >= $settings['minAmount'] ) {

			$amount = WC()->cart->total;

		}

		// Set amount to product price when on single product page
		if ( is_product() && $product->get_regular_price() <= $settings['maxAmount'] && $product->get_regular_price() >= $settings['minAmount'] ) {

			$amount = $product->get_regular_price();

		}

		// Execute API call and fetch results
		$apiOfferArrayFirst = $this->get_api_response_calculate(
			$settings['apiurl'].'calculate',
			array(
				'loanAmount' => (int)$amount,
				'durationInMonths' => 24,
			)
		);

		// Execute API call and fetch results
		$apiOfferArraySecond = $this->get_api_response_calculate(
			$settings['apiurl'].'calculate',
			array(
				'loanAmount' => (int)$amount,
				'durationInMonths' => 12,
			)
		);

		// Execute API call and fetch results
		$apiOfferArrayThird = $this->get_api_response_calculate(
			$settings['apiurl'].'calculate',
			array(
				'loanAmount' => (int)$amount,
				'durationInMonths' => 60,
			)
		);

		$tableTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-table.html');

		$tableTemplate = str_replace(
				array(
					'{{totalAmount}}',
					'{{totalCosts-24}}',
					'{{effectiveAnnualRate-24}}',
					'{{termPayment-24}}',
					'{{totalCosts-12}}',
					'{{effectiveAnnualRate-12}}',
					'{{termPayment-12}}',
					'{{totalCosts-60}}',
					'{{effectiveAnnualRate-60}}',
					'{{termPayment-60}}'
				),
				array(
					number_format( $amount, 2, ',', '.' ),
					number_format( $apiOfferArrayFirst['totalCosts'], 2, ',', '.' ),
					number_format( $apiOfferArrayFirst['effectiveAnnualRate'], 2, ',', '.' ),
					number_format( $apiOfferArrayFirst['termPayment'], 2, ',', '.' ),
					number_format( $apiOfferArraySecond['totalCosts'], 2, ',', '.' ),
					number_format( $apiOfferArraySecond['effectiveAnnualRate'], 2, ',', '.' ),
					number_format( $apiOfferArraySecond['termPayment'], 2, ',', '.' ),
					number_format( $apiOfferArrayThird['totalCosts'], 2, ',', '.' ),
					number_format( $apiOfferArrayThird['effectiveAnnualRate'], 2, ',', '.' ),
					number_format( $apiOfferArrayThird['termPayment'], 2, ',', '.' ),
				),
				$tableTemplate
			);

		return $tableTemplate;

	}

	/**
	 * Findio loan total Shortcodes
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string HTML formatted price for loan total (used in cart)
	 */
	public function generate_loan_total_shortcode() {

		global $woocommerce;

		// Set price
		$amount = number_format( WC()->cart->total, 0, '.', false );

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Execute API call and fetch results
		$apiOfferArray = $this->get_api_response_calculate(
			$settings['apiurl'].'calculate',
			array(
				'loanAmount' => (int)$amount,
				'durationInMonths' => $settings['durationInMonths'],
			)
		);

		if ( $amount <= $settings['maxAmount'] && $amount >= $settings['minAmount'] ) { // If amount is within limits

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				echo json_encode(
					array(
						'status' => 200,
						'html' => $apiOfferArray['cart'],
						'termPayment' => '&euro;' . number_format_i18n( (float)$apiOfferArray['termPayment'], 2 ),
					)
				);

				wp_die();

			} else {

				// Return template
				return $apiOfferArray['cart'];

			}


		} else {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				echo json_encode(
					array(
						'status' => 200,
						'html' => $apiOfferArray['cartmaxedout'],
						'termPayment' => '&euro;' . number_format_i18n( (float)$apiOfferArray['termPayment'], 2 ),
					)
				);

				wp_die();

			} else {

				// Return template
				return $apiOfferArray['cartmaxedout'];

			}

		}

		//bstcmfw_write_log("----generate_loan_total_shortcode----");
		//bstcmfw_write_log($apiOfferArray);

	}

	/**
	 * Findio loan single Shortcodes
	 *
	 * @since 1.0.0
	 * @param none
	 * @return string HTML formatted loan text (used on single templates)
	 */
	public function generate_loan_single_shortcode() {

		global $woocommerce;

		// Set price
		$amount = number_format( WC()->cart->total, 0, '.', false );

		// Gather options
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Execute API call and fetch results
		$apiOfferArray = $this->get_api_response_calculate(
			$settings['apiurl'].'calculate',
			array(
				'loanAmount' => (int)$amount,
				'durationInMonths' => $settings['durationInMonths'],
			)
		);

		if ( $amount <= $settings['maxAmount'] && $amount >= $settings['minAmount'] ) { // If amount is within limits

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				echo json_encode(
					array(
						'status' => 200,
						'html' => $apiOfferArray['cart'],
						'termPayment' => '&euro;' . number_format_i18n( (float)$apiOfferArray['termPayment'], 2 ),
					)
				);

				wp_die();

			} else {

				// Return template
				return $apiOfferArray['cart'];

			}


		} else {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				echo json_encode(
					array(
						'status' => 200,
						'html' => $apiOfferArray['cartmaxedout'],
						'termPayment' => '&euro;' . number_format_i18n( (float)$apiOfferArray['termPayment'], 2 ),
					)
				);

				wp_die();

			} else {

				// Return template
				return $apiOfferArray['cartmaxedout'];

			}

		}

		//bstcmfw_write_log("----generate_loan_total_shortcode----");
		//bstcmfw_write_log($apiOfferArray);

	}

	/**
	 * Payment gateway API connection method
	 *
	 * @since 1.0.0
	 * @param string $apiEndpoint
	 * @param array $apiArguments
	 * @return array array with API response
	 */

	public function get_api_response_calculate( $apiEndpoint, $apiArguments ) {

	    // Fetch settings from gateway
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

	    // Build api parameters and generate transient key based on body arguments
	    $apiBody            = json_encode( $apiArguments, JSON_NUMERIC_CHECK );
	    $apiTransientKey    = hash( 'ripemd160', implode( $apiArguments ) );
	    $apiAccount         = $settings['apiusername'];
	    $apiKey             = $settings['apikey'];
	    $apiAuth            = "ApiKey {$apiAccount}:{$apiKey}";

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

		//bfg_write_log( "-- API RESULT --");
		//bfg_write_log( $apiEndpoint );
		//bfg_write_log( $apiArguments );
		//bfg_write_log( $apiResponse );

	    // Load template HTML
		$singleTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-offer.html');
		$maxedoutTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-maxedout.html');
		$cartmaxedoutTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-cart-maxedout.html');
		$maxedoutSingleTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-maxedout-single.html');
		$blockTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-checkout.html');
		$shortcodeTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-shortcode.html');
		$cartTemplate = file_get_contents( BFG_PLUGIN_DIR . '/templates/bstcm-findio-cart.html');

	    // If error occured (status code is not 200)
	    if ( is_wp_error( $apiResponse ) ){

	        return array(
		        'status' => 500,
	            'message' => $apiResponse->get_error_message(),
	            'endpoint' => $apiEndpoint,
	            'arguments' => $apiArguments,
	        );

		} elseif ( 200 != $apiResponse['response']['code'] ) {

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
			$singleTemplate = str_replace(
				array(
					'{{findiologo}}',
					'{{findiobanner}}',
					'{{totalCosts}}',
					'{{durationInMonths}}',
					'{{nominalAnnualRate}}',
					'{{termPayment}}',
					'{{loanAmount}}',
					'{{effectiveAnnualRate}}',
				),
				array(
					BFG_PLUGIN_URL.'assets/img/findio-logo.svg', // findiologo
					BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
					number_format_i18n( (float)$apiResult->totalCosts, 2 ), // totalCosts
					(int)$apiResult->durationInMonths, // durationInMonths
					(float)$apiResult->nominalAnnualRate, // nominalAnnualRate
					number_format_i18n( (float)$apiResult->termPayment, 2 ), // termPayment
					number_format_i18n( (float)$apiResult->loanAmount, 2 ), // loanAmount
					(float)$apiResult->effectiveAnnualRate, // effectiveAnnualRate
				),
				$singleTemplate
			);

			// Replace template HTML values with results
			$cartmaxedoutTemplate = str_replace(
				array(
					'{{findiologo}}',
					'{{findiobanner}}',
					'{{maxAmount}}',
					'{{minAmount}}',
				),
				array(
					BFG_PLUGIN_URL.'assets/img/findio-logo.svg', // findiologo
					BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
					number_format_i18n( (float)$settings['maxAmount'], 2 ), // maxAmount
					number_format_i18n( (float)$settings['minAmount'], 2 ), // maxAmount
				),
				$cartmaxedoutTemplate
			);

			// Replace template HTML values with results
			$maxedoutTemplate = str_replace(
				array(
					'{{findiologo}}',
					'{{findiobanner}}',
					'{{maxAmount}}',
					'{{minAmount}}',
				),
				array(
					BFG_PLUGIN_URL.'assets/img/findio-logo.svg', // findiologo
					BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
					number_format_i18n( (float)$settings['maxAmount'], 2 ), // maxAmount
					number_format_i18n( (float)$settings['minAmount'], 2 ), // maxAmount
				),
				$maxedoutTemplate
			);

	        // Replace template HTML values with results
	        $blockTemplate = str_replace(
	            array(
	                '{{findiologo}}',
	                '{{findiobanner}}',
	                '{{totalCosts}}',
	                '{{durationInMonths}}',
	                '{{nominalAnnualRate}}',
	                '{{termPayment}}',
	                '{{loanAmount}}',
	                '{{effectiveAnnualRate}}',
	            ),
	            array(
	                BFG_PLUGIN_URL.'assets/img/findio-logo.svg', // findiologo
	                BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
	                number_format_i18n( (float)$apiResult->totalCosts, 2 ), // totalCosts
	                (int)$apiResult->durationInMonths, // durationInMonths
	                (float)$apiResult->nominalAnnualRate, // nominalAnnualRate
	                number_format_i18n( (float)$apiResult->termPayment, 2 ), // termPayment
	                number_format_i18n( (float)$apiResult->loanAmount, 2 ), // loanAmount
	                (float)$apiResult->effectiveAnnualRate, // effectiveAnnualRate
	            ),
	            $blockTemplate
	        );

			$shortcodeTemplate = str_replace(
	            array(
					'{{findiobanner}}',
	                '{{totalCosts}}',
	                '{{durationInMonths}}',
	                '{{nominalAnnualRate}}',
	                '{{termPayment}}',
	                '{{loanAmount}}',
	                '{{effectiveAnnualRate}}',
	            ),
	            array(
					BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
	                number_format_i18n( (float)$apiResult->totalCosts, 2 ), // totalCosts
	                $settings['durationInMonths'], // durationInMonths
	                (float)$apiResult->nominalAnnualRate, // nominalAnnualRate
	                number_format_i18n( (float)$apiResult->termPayment, 2 ), // termPayment
	                number_format_i18n( (float)$apiResult->loanAmount, 2 ), // loanAmount
	                (float)$apiResult->effectiveAnnualRate, // effectiveAnnualRate
	            ),
	            $shortcodeTemplate
	        );

			$maxedoutSingleTemplate = str_replace(
	            array(
					'{{findiobanner}}',
	                '{{totalCosts}}',
	                '{{durationInMonths}}',
	                '{{nominalAnnualRate}}',
	                '{{termPayment}}',
	                '{{loanAmount}}',
	                '{{effectiveAnnualRate}}',
					'{{maxAmount}}',
	            ),
	            array(
					BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
	                number_format_i18n( (float)$apiResult->totalCosts, 2 ), // totalCosts
	                $settings['durationInMonths'], // durationInMonths
	                (float)$apiResult->nominalAnnualRate, // nominalAnnualRate
	                number_format_i18n( (float)$apiResult->termPayment, 2 ), // termPayment
	                number_format_i18n( (float)$apiResult->loanAmount, 2 ), // loanAmount
	                (float)$apiResult->effectiveAnnualRate, // effectiveAnnualRate
					number_format_i18n( (float)$settings['maxAmount'], 2 ), // maxAmount
	            ),
	            $maxedoutSingleTemplate
	        );



	        //bstcmfw_write_log("API RESULT - SHORTCODE");
	        //bstcmfw_write_log($apiResult->durationInMonths);
	        //bstcmfw_write_log($shortcodeTemplate);

			$cartTemplate = str_replace(
	            array(
					'{{findiologo}}',
					'{{findiobanner}}',
	                '{{totalCosts}}',
	                '{{durationInMonths}}',
	                '{{nominalAnnualRate}}',
	                '{{termPayment}}',
	                '{{loanAmount}}',
	                '{{effectiveAnnualRate}}',
	            ),
	            array(
					BFG_PLUGIN_URL.'assets/img/findio-logo.svg', // findiologo
					BFG_PLUGIN_URL.'assets/img/geldlenenkostgeld-banner.svg', // findiobanner
	                number_format_i18n( (float)$apiResult->totalCosts, 2 ), // totalCosts
	                (int)$apiResult->durationInMonths, // durationInMonths
	                (float)$apiResult->nominalAnnualRate, // nominalAnnualRate
	                number_format_i18n( (float)$apiResult->termPayment, 2 ), // termPayment
	                number_format_i18n( (float)$apiResult->loanAmount, 2 ), // loanAmount
	                (float)$apiResult->effectiveAnnualRate, // effectiveAnnualRate
	            ),
	            $cartTemplate
	        );

	        // Return results array back to caller function
	        return array(
	            'status' => $apiResponse['response']['code'],
	            'html' => $blockTemplate,
				'shortcode' => $shortcodeTemplate,
				'cart' => $cartTemplate,
				'single' => $singleTemplate,
				'cartmaxedout' => $cartmaxedoutTemplate,
				'maxedout' => $maxedoutTemplate,
	            'endpoint' => $apiEndpoint,
	            'arguments' => $apiArguments,
	            'totalCosts' => (float)$apiResult->totalCosts,
	            'durationInMonths' => (int)$apiResult->durationInMonths,
	            'effectiveAnnualRate' => (float)$apiResult->effectiveAnnualRate,
	            'termPayment' => (float)$apiResult->termPayment,
				'maxedoutSingle' => $maxedoutSingleTemplate,
	        );

	    }

	}

	/**
	 * Payment gateway API connection method
	 *
	 * @since 1.0.0
	 * @param string $apiEndpoint
	 * @param array $apiArguments
	 * @return array array with API response
	 */

	public function get_api_response_request( $apiEndpoint, $apiArguments ) {

		// Fetch settings from gateway
		$settings = get_option('woocommerce_bfg_gateway_settings', array() );

		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
		bfg_write_log( 'get_api_response_request()');
		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
		bfg_write_log( 'apiEndpoint: ' . $apiEndpoint );
		bfg_write_log( 'apiArguments: ');
		bfg_write_log( $apiArguments );

		// Build api parameters and generate transient key based on body arguments
		$apiBody            = json_encode( $apiArguments, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION );
		$apiTransientKey    = hash( 'ripemd160', implode( $apiArguments ) );
		$apiAccount         = $settings['apiusername'];
		$apiKey             = $settings['apikey'];
		$apiAuth            = "ApiKey {$apiAccount}:{$apiKey}";

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

			// Return results array back to caller function
			return array(
				'status' => $apiResponse['response']['code'],
				'result' => $apiResult,
				'requesturl' => $apiResult->redirectURL,
				'endpoint' => $apiEndpoint,
				'arguments' => $apiArguments,
			);

		}

	}


	/**
	 * Payment gateway API callback functions
	 *
	 * @since 1.0.0
	 * @param string $json
	 * @return string with status code header
	 */

	public function bfg_gateway_callback() {

		global $woocommerce;

		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');
		bfg_write_log( 'bfg_gateway_callback()');
		bfg_write_log( '- - - - - - - - - - - - - - - - - - - - - - - ');

		/*
		noRequest -> de klant heeft vóór het doen van een aanvraag op de knop annuleren gedrukt (nieuw)
		inProgress -> er is een aanvraag gedaan (bestaande functionaliteit, dubbelcheck nodig of deze nog werkt zoals required)
		preApproved -> de aanvraag is voorlopig akkoord (nieuw, nog niet gezien bij het testen van 24)
		approved -> de aanvraag is definitief akkoord en uitbetaald (bestaande functionaliteit)
		cancelled -> de aanvraag is gedaan, maar door de klant geannuleerd (nieuw)
		rejected -> er zijn geen mogelijkheden voor de klant (bestaande functionaliteit, nog niet gezien bij het testen van 24)
		expired -> de aanvraag is komen te vervallen (bestaande functionaliteit)
		*/

		// Map Findio status to WooCommerce status
		$statusses = array(
			'noRequest' => array(
				'status' => 'completed',
				'note' => __( 'Loan request cancelled by user', 'bstcm-findio-gateway' )
			),
			'inProgress' => array(
				'status' => 'on-hold',
				'note' => __( 'Awaiting loan request approval', 'bstcm-findio-gateway' )
			),
			'preApproved' => array(
				'status' => 'cancelled',
				'note' => __( 'Loan request pre approved', 'bstcm-findio-gateway' )
			),
			'approved' => array(
				'status' => 'completed',
				'note' => __( 'Loan request approved', 'bstcm-findio-gateway' )
			),
			'cancelled' => array(
				'status' => 'cancelled',
				'note' => __( 'Loan request cancelled by user', 'bstcm-findio-gateway' )
			),
			'rejected' => array(
				'status' => 'cancelled',
				'note' => __( 'Loan request rejected', 'bstcm-findio-gateway' )
			),
			'expired' => array(
				'status' => 'failed',
				'note' => __( 'Loan request timed-out', 'bstcm-findio-gateway' )
			),
			'unknown' => array(
				'status' => 'on-hold',
				'note' => __( 'Loan request unable to verify', 'bstcm-findio-gateway' )
			),
		);

		// Fetch jSON body from POST
		$body = @file_get_contents('php://input');

		// Decode JSON
		$callbackArguments = json_decode( $body );

		bstcmfw_write_log( $body ); // Output JSON body to debug.log

		// Build validation hash
		$hashString = $callbackArguments->orderID."|".$callbackArguments->requestID."|".$callbackArguments->status;
		$hashSecret = $this->get_option( 'apisecret' );
		$hashValidate = base64_encode( hash_hmac( 'sha256', $hashString, $hashSecret ) );

		// Update order status if hash is valid and output 200 status
		if ( $hashValidate == $callbackArguments->hash ) {

				$order = new WC_Order( $callbackArguments->orderID );

				if ( isset( $statusses[$callbackArguments->status] ) ) {

					// Update status based on API callback
					$order->update_status( $statusses[$callbackArguments->status]['status'], $statusses[$callbackArguments->status]['note'] );

				} else {

					// Unknown status, set 'unknown'
					$order->update_status( $statusses['unknown']['status'], $statusses['unknown']['note'] );

				}

				wp_die(
					'Order #'.$callbackArguments->orderID.' updated to status '.$statusses[$callbackArguments->status]['status'],
					'Order updated',
					array(
						'response' => 200
					)
				);

		// Reject call and output 401 status
		} else {

				wp_die(
					'Hash values do not match (expected '.$hashValidate.')',
					'Error while updating order',
					array(
						'response' => 401
					)
				);

		}

	}

	/**
	 * Payment gateway API test method
	 *
	 * @since 1.0.0
	 * @param string $apiEndpoint string $apiArguments
	 * @param array $apiArguments
	 * @return array array with API response
	 */
	public function bfg_api_test() {

	    // Fetch settings from gateway
	    $settings = get_option('woocommerce_bfg_gateway_settings', array() );

		// Build api parameters and generate transient key based on body arguments
	    $apiBody            = json_encode( array(), JSON_NUMERIC_CHECK );
	    $apiTransientKey    = hash( 'ripemd160', null );
	    $apiAccount         = $settings['apiusername'];
	    $apiKey             = $settings['apikey'];
	    $apiAuth            = "ApiKey {$apiAccount}:{$apiKey}";
		$apiEndpoint		= $settings['apiurl'].'/calculate/';

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

		if ( is_wp_error( $apiResponse ) ) {
			return $apiResponse;
		} else {
			return array(
				'statuscode' => $apiResponse['code'],
			    'status' => $apiResponse,
			    'ip' => $_SERVER['SERVER_ADDR'],
			    'endpoint' => $apiEndpoint,
			);
		}

	}

}
