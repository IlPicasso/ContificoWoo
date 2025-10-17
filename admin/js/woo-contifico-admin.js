/* global jQuery, woo_contifico_globals, ajaxurl, woocommerce_admin_meta_boxes */
(function( $ ) {
	'use strict';

        // DOM ready
        $(function() {
                const pluginGlobals = ( typeof woo_contifico_globals !== 'undefined' ) ? woo_contifico_globals : {};
                const messages      = pluginGlobals.messages || {};

                function renderSingleSyncResult( $container, data ) {
                        const changes        = data.changes || {};
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
                                $container.append( $( '<p />' ).text( data.message ) );
                        }

                        $container.append( $list );
                        $container.addClass( 'success' ).show();
                }

		// Fetch products manually in settings page
		/** @param {{plugin_name, woo_nonce}} woo_contifico_globals */
		$( '#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .button').on('click', function(){

			// Reset results
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .button').hide();
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products p').hide();
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products img').fadeIn();
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .fetched').html(0);
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .found').html(0);
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .updated').html(0);
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .outofstock').html(0);
			$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result').fadeIn();

			// Start batch
			batch_processing(1);
		});

		// Batch processing
                function batch_processing(step) {

			let data = {
				'action': 'fetch_products',
				'security': pluginGlobals.woo_nonce,
				'step': step
			};

			$.ajax({
				type: 'post',
				url: ajaxurl,
				data: data,
				success: function (response) {

					/** @param {{step, fetched, found, outofstock}} response */
					if( 'done' === response.step ) {
						$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products img').fadeOut();
					}
					else {
						$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .fetched').html(response.fetched);
						$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .found').html(response.found);
						$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .updated').html(response.updated);
						$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result .outofstock').html(response.outofstock);

						// Call recursively
						batch_processing(response.step);
					}

				},
				error: function (response) {
					$('#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result').html(response.responseText);
				}
			});

                }

                // Synchronize a single product by SKU
                $( '#' + pluginGlobals.plugin_name + '-settings-page .fetch-single-product' ).each( function () {
                        const $container     = $( this );
                        const $button        = $container.find( '.button' );
                        const $skuInput      = $container.find( 'input[name="woo_contifico_single_sku"]' );
                        const $spinner       = $container.find( 'img' );
                        const $result        = $container.find( '.result' );
                        const emptyMessage   = $container.data( 'empty-message' ) || '';
                        const genericError   = $container.data( 'generic-error' ) || '';
                        $button.on( 'click', function ( event ) {
                                event.preventDefault();

                                const sku = $.trim( $skuInput.val() );

                                $result.removeClass( 'error success' ).empty().hide();

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
                                                security: pluginGlobals.woo_nonce,
                                                sku:      sku
                                        }
                                } ).done( function ( response ) {

                                        if ( response && response.success && response.data ) {
                                                renderSingleSyncResult( $result, response.data );
                                        }
                                        else {
                                                const message = ( response && response.data && response.data.message ) ? response.data.message : genericError;

                                                if ( message ) {
                                                        $result.addClass( 'error' ).text( message ).show();
                                                }
                                        }
                                } ).fail( function ( xhr ) {

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
                                } ).always( function () {
                                        $spinner.fadeOut();
                                        $button.prop( 'disabled', false );
                                } );
                        } );
                } );

                $( '.woo-contifico-product-id-field' ).each( function () {
                        const $field            = $( this );
                        const $button           = $field.find( '.woo-contifico-sync-product-button' );
                        const $spinner          = $field.find( '.woo-contifico-sync-spinner' );
                        const $result           = $field.find( '.woo-contifico-sync-result' );
                        const genericError      = $field.data( 'generic-error' ) || '';
                        const missingIdentifier = $field.data( 'missing-identifier' ) || '';
                        const productId         = parseInt( $field.data( 'product-id' ), 10 ) || 0;

                        if ( ! $button.length ) {
                                return;
                        }

                        $button.on( 'click', function ( event ) {
                                event.preventDefault();

                                const $skuInput    = $( '#_sku' );
                                const skuFromAttr  = $.trim( $field.data( 'product-sku' ) || '' );
                                const skuFromInput = $skuInput.length ? $.trim( $skuInput.val() ) : '';
                                const sku          = skuFromAttr || skuFromInput;

                                $result.removeClass( 'error success' ).empty().hide();
                                $button.prop( 'disabled', true );
                                $spinner.addClass( 'is-active' );

                                const requestData = {
                                        action:   'woo_contifico_sync_single_product',
                                        security: pluginGlobals.woo_nonce
                                };

                                if ( sku ) {
                                        requestData.sku = sku;
                                }

                                if ( productId > 0 ) {
                                        requestData.product_id = productId;
                                }

                                $.ajax( {
                                        type: 'post',
                                        url: ajaxurl,
                                        data: requestData
                                } ).done( function ( response ) {
                                        if ( response && response.success && response.data ) {
                                                const data = response.data;

                                                renderSingleSyncResult( $result, data );

                                                if ( data.woocommerce_sku ) {
                                                        $field.attr( 'data-product-sku', data.woocommerce_sku );
                                                        $field.data( 'product-sku', data.woocommerce_sku );

                                                        if ( $skuInput.length && $.trim( $skuInput.val() || '' ) === '' ) {
                                                                $skuInput.val( data.woocommerce_sku );
                                                        }
                                                }

                                                if ( data.contifico_id ) {
                                                        const $value   = $field.find( '.woo-contifico-product-id-value' );
                                                        const $missing = $field.find( '.woo-contifico-product-id-missing' );
                                                        const $newNode = $( '<span />', {
                                                                'class': 'woo-contifico-product-id-value',
                                                                text:    data.contifico_id
                                                        } );

                                                        if ( $value.length ) {
                                                                $value.replaceWith( $newNode );
                                                        } else if ( $missing.length ) {
                                                                $missing.replaceWith( $newNode );
                                                        } else {
                                                                $field.find( '.form-field' ).append( $newNode );
                                                        }
                                                } else if ( missingIdentifier ) {
                                                        const $value   = $field.find( '.woo-contifico-product-id-value' );
                                                        const $missing = $field.find( '.woo-contifico-product-id-missing' );

                                                        if ( $value.length ) {
                                                                $value.replaceWith( $( '<span />', {
                                                                        'class': 'woo-contifico-product-id-missing',
                                                                        text:   missingIdentifier
                                                                } ) );
                                                        } else if ( ! $missing.length ) {
                                                                $field.find( '.form-field' ).append( $( '<span />', {
                                                                        'class': 'woo-contifico-product-id-missing',
                                                                        text:   missingIdentifier
                                                                } ) );
                                                        }
                                                }
                                        } else {
                                                const message = ( response && response.data && response.data.message ) ? response.data.message : genericError;

                                                if ( message ) {
                                                        $result.addClass( 'error' ).text( message ).show();
                                                }
                                        }
                                } ).fail( function ( xhr ) {
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
                                } ).always( function () {
                                        $button.prop( 'disabled', false );
                                        $spinner.removeClass( 'is-active' );
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
