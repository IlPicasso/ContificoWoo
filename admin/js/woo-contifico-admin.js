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

                // Synchronize a single product by SKU
                $( '#' + woo_contifico_globals.plugin_name + '-settings-page .fetch-single-product' ).each( function () {
                        const $container     = $( this );
                        const $button        = $container.find( '.button' );
                        const $skuInput      = $container.find( 'input[name="woo_contifico_single_sku"]' );
                        const $spinner       = $container.find( 'img' );
                        const $result        = $container.find( '.result' );
                        const emptyMessage   = $container.data( 'empty-message' ) || '';
                        const genericError   = $container.data( 'generic-error' ) || '';
                        const messages       = woo_contifico_globals.messages || {};

                        $button.on( 'click', function ( event ) {
                                event.preventDefault();

                                const sku = $.trim( $skuInput.val() );

                                $result.removeClass( 'error success' ).empty();

                                if ( '' === sku ) {
                                        if ( emptyMessage ) {
                                                $result.addClass( 'error' ).text( emptyMessage ).show();
                                        }

                                        return;
                                }

                                $button.prop( 'disabled', true );
                                $spinner.fadeIn();

                                $.ajax( {
                                        type: 'post',
                                        url: ajaxurl,
                                        data: {
                                                action:   'woo_contifico_sync_single_product',
                                                security: woo_contifico_globals.woo_nonce,
                                                sku:      sku
                                        }
                                } ).done( function ( response ) {
                                        $spinner.fadeOut();
                                        $button.prop( 'disabled', false );

                                        if ( response && response.success && response.data ) {
                                                const data = response.data;
                                                const changes = data.changes || {};
                                                const changeMessages = [];

                                                if ( changes.stock_updated ) {
                                                        changeMessages.push( messages.stockUpdated || 'Inventario actualizado.' );
                                                }

                                                if ( changes.price_updated ) {
                                                        changeMessages.push( messages.priceUpdated || 'Precio actualizado.' );
                                                }

                                                if ( changes.meta_updated ) {
                                                        changeMessages.push( messages.metaUpdated || 'Identificador actualizado.' );
                                                }

                                                if ( changes.outofstock ) {
                                                        changeMessages.push( messages.outOfStock || 'Producto sin stock.' );
                                                }

                                                if ( changeMessages.length === 0 ) {
                                                        changeMessages.push( messages.noChanges || 'Sin cambios en inventario ni precio.' );
                                                }

                                                const $list = $( '<ul />' );

                                                if ( data.woocommerce_sku ) {
                                                        $list.append( $( '<li />' ).text( ( messages.wooSkuLabel || 'SKU en WooCommerce:' ) + ' ' + data.woocommerce_sku ) );
                                                }

                                                if ( data.contifico_sku ) {
                                                        $list.append( $( '<li />' ).text( ( messages.contificoSkuLabel || 'SKU en Contífico:' ) + ' ' + data.contifico_sku ) );
                                                }

                                                if ( data.contifico_id ) {
                                                        $list.append( $( '<li />' ).text( ( messages.contificoIdLabel || 'ID de Contífico:' ) + ' ' + data.contifico_id ) );
                                                }

                                                if ( typeof data.stock_quantity === 'number' ) {
                                                        $list.append( $( '<li />' ).text( ( messages.stockLabel || 'Inventario disponible:' ) + ' ' + data.stock_quantity ) );
                                                }

                                                if ( typeof data.price === 'number' ) {
                                                        $list.append( $( '<li />' ).text( ( messages.priceLabel || 'Precio actual:' ) + ' ' + data.price ) );
                                                }

                                                $list.append( $( '<li />' ).text( ( messages.changesLabel || 'Cambios detectados:' ) + ' ' + changeMessages.join( ' ' ) ) );

                                                if ( data.message ) {
                                                        $result.append( $( '<p />' ).text( data.message ) );
                                                }

                                                $result.append( $list );
                                                $result.addClass( 'success' ).show();
                                        }
                                        else {
                                                const message = ( response && response.data && response.data.message ) ? response.data.message : genericError;

                                                if ( message ) {
                                                        $result.addClass( 'error' ).text( message ).show();
                                                }
                                        }
                                } ).fail( function ( xhr ) {
                                        $spinner.fadeOut();
                                        $button.prop( 'disabled', false );

                                        let message = genericError;

                                        if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
                                                message = xhr.responseJSON.data.message;
                                        }
                                        else if ( xhr.responseText ) {
                                                message = xhr.responseText;
                                        }

                                        if ( message ) {
                                                $result.addClass( 'error' ).text( message ).show();
                                        }
                                } );
                        } );
                } );

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
