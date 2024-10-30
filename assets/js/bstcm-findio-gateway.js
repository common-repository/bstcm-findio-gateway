/**
  * WooCommerce checkout Findio loan offer block
  *
  * @since 1.0.0
  * @return string HTML block with loan offer
  */
jQuery( document ).on(
    'updated_checkout',
    function() {

        jQuery.ajax({
			type: 'POST',
			url: the_ajax_script.bfg_ajaxurl,
			data: {
                'action' : 'bfg_get_woocommerce_loan_offer_shortcode_hook',
            },
			dataType: 'JSON',
			success: function(data) {

                // Remove existing Findio wrappers
                jQuery( '#findio-wrapper' ).remove();

                if ( 500 == data.status ) {

					alert( data.message );

                    return false;

                } else if ( 200 == data.status ) {

                    // Output generated HTML content block
                    jQuery( data.html ).appendTo( '.wc_payment_method.payment_method_bfg_gateway' );

                    // Show generated HTML block if payment option is selected
                    if ( jQuery( 'input#payment_method_bfg_gateway:checked' ).length ) {

	                	jQuery( '#findio-wrapper' ).css({ 'display' : 'block' });

                    }

                }

            }

        });

    }
);

jQuery( document ).on(
    'updated_cart_totals',
    function() {

        jQuery('.findio-cart').addClass('findio-cart--loading');

        jQuery.ajax({
			type: 'POST',
			url: the_ajax_script.bfg_ajaxurl,
			data: {
                'action' : 'bfg_add_to_collaterals_hook',
            },
			dataType: 'JSON',
			success: function(data) {

                if ( 500 == data.status ) {

					alert( data.message );

                    return false;

                } else if ( 200 == data.status ) {

                    // Output generated HTML content block
                    jQuery('.findio-cart').html(data.html);
                    jQuery('.findio-cart > .findio-cart').unwrap();
                    jQuery('.findio-cart').removeClass('findio-cart--loading');

                }

            }

        });


    }
);

//wc_update_cart

/**
  * WooCommerce update loan offer on variation selection
  *
  * @since 1.0.0
  * @return string HTML formatted term payment amount
  */
jQuery( '.single_variation_wrap' ).on(
    'show_variation',
    function( event, variation ) {

        console.log('show_variation fired');
        console.log(variation);

        if ( jQuery( 'body' ).hasClass( 'single-product' ) ) {

            jQuery( '#findio-wrapper .findio-offer-table .findio__maxedout' ).remove();

			jQuery( '#termPayment' ).html('<i class="fa fa-fw fa-refresh fa-spin" style=""></i>');

            jQuery.ajax(
                {
                    type: 'POST',
                    url: the_ajax_script.bfg_ajaxurl,
                    data: {
                        'action' : 'bfg_generate_loan_offer_shortcode_hook',
                        'variationPrice' : variation.display_price,
                    },
                    dataType: 'JSON',
                    success: function(data) {

						console.log(data);

                        if ( 200 != data.status && 300 != data.status ) {

                            // Output error as JS alert
                            alert( "Fout tijdens ophalen van Findio aanbod: " + data.message );
                            return false;

                        } else if ( 300 == data.status ) {

                            // Inject new term payment amount into HTML container
                            jQuery( '#findio-wrapper .findio-offer-table' ).append( data.html );
                            jQuery( '#termPayment' ).html( '-' );

                        } else if ( 200 == data.status ) {

                            // Inject new term payment amount into HTML container
                            jQuery( '#termPayment' ).html( data.termPayment );

                        }

                    }
                }
            );

        }
    }
);

function bfg_test_connection() {
	'use strict';

    jQuery.ajax({
		type: 'POST',
		url: the_ajax_script.bfg_ajaxurl,
		data: {
            'action' : 'bfg_test_api_connection_hook',
        },
		dataType: 'JSON',
		success: function(data) {

			console.log( data );

        }

    });

}
