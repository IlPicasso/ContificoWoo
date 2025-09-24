/* global jQuery, woo_contifico_globals, ajaxurl, woocommerce_admin_meta_boxes */
(function( $ ) {
	'use strict';

	// DOM ready
	$(function() {

		// Fetch products manually in settings page
		/** @param {{plugin_name, woo_nonce}} woo_contifico_globals */
		$( '#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .button').on('click', function(){

			// Reset results
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .button').hide();
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products p').hide();
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products img').fadeIn();
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .fetched').html(0);
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .found').html(0);
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .updated').html(0);
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .outofstock').html(0);
			$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result').fadeIn();

			// Start batch
			batch_processing(1);
		});

		// Batch processing
		function batch_processing(step) {

			let data = {
				'action': 'fetch_products',
				'security': woo_contifico_globals.woo_nonce,
				'step': step
			};

			$.ajax({
				type: 'post',
				url: ajaxurl,
				data: data,
				success: function (response) {

					/** @param {{step, fetched, found, outofstock}} response */
					if( 'done' === response.step ) {
						$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products img').fadeOut();
					}
					else {
						$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .fetched').html(response.fetched);
						$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .found').html(response.found);
						$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .updated').html(response.updated);
						$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result .outofstock').html(response.outofstock);

						// Call recursively
						batch_processing(response.step);
					}

				},
				error: function (response) {
					$('#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-products .result').html(response.responseText);
				}
			});

		}

		// Load tax info when looking for customer in order backend
		$('#order_data .wc-customer-user select.wc-customer-search').change(function(){

			//Call Ajax
			/** @param {{get_customer_details_nonce}} woocommerce_admin_meta_boxes */
			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: {
					user_id:      this.value,
					action:       'woocommerce_get_customer_details',
					security:     woocommerce_admin_meta_boxes.get_customer_details_nonce
				},
				success: function (response) {
					/** @param {{meta_data}} response */
					let metadata = response.meta_data;
					let i, tax_type, tax_id;
					let tax_subject = 0;
					for( i = 0; i < metadata.length; i++ ) {
						switch ( metadata[i].key ) {
							case 'tax_subject':
								tax_subject = metadata[i].value;
								break;
							case 'tax_type':
								tax_type = metadata[i].value;
								break;
							case 'tax_id':
								tax_id = metadata[i].value;
								break;
						}
					}

					$('#order_data .tax_subject_field #tax_subject').prop('checked', tax_subject);
					$('#order_data .tax_type_field #tax_type').val( tax_type );
					$('#order_data .tax_id_field #tax_id').val( tax_id );

				}
			});

		});

	});

})( jQuery );
