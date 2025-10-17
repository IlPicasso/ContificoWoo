/* global jQuery, woo_contifico_globals, ajaxurl, woocommerce_admin_meta_boxes */
(function( $ ) {
	'use strict';

        // DOM ready
        $(function() {
                const pluginGlobals  = ( typeof woo_contifico_globals !== 'undefined' ) ? woo_contifico_globals : {};
                const messages       = pluginGlobals.messages || {};
                const ajaxEndpoint   = pluginGlobals.ajaxUrl || ( typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '' );
                const syncingMessage = messages.syncing || '';
                const namespace      = window.wooContificoAdmin = window.wooContificoAdmin || {};

                namespace.pluginGlobals = pluginGlobals;
                namespace.messages       = messages;

                function formatChangeValue( previous, next, separator, fallback ) {
                        const hasPrevious = typeof previous === 'number';
                        const hasNext     = typeof next === 'number';

                        if ( hasPrevious && hasNext ) {
                                if ( previous === next ) {
                                        return String( next );
                                }

                                return previous + ' ' + separator + ' ' + next;
                        }

                        if ( hasNext ) {
                                return String( next );
                        }

                        if ( hasPrevious ) {
                                return String( previous );
                        }

                        return fallback;
                }

                function formatIdentifierValue( previous, next, separator, fallback ) {
                        const safePrevious = previous || '';
                        const safeNext     = next || '';

                        if ( safePrevious && safeNext ) {
                                if ( safePrevious === safeNext ) {
                                        return safeNext;
                                }

                                return safePrevious + ' ' + separator + ' ' + safeNext;
                        }

                        if ( safeNext ) {
                                return safeNext;
                        }

                        if ( safePrevious ) {
                                return safePrevious + ' ' + separator + ' ' + fallback;
                        }

                        return fallback;
                }

                function renderSingleSyncResult( $container, data ) {
                        const changes             = data.changes || {};
                        const changeMessages      = [];
                        const separator           = messages.changeSeparator || '→';
                        const noValue             = messages.noValue || 'N/D';
                        const noIdentifier        = messages.noIdentifier || 'Sin identificador registrado.';
                        const detailSummaryLabel  = messages.changeDetailSummary || 'Detalle sincronizado:';
                        const detailJoiner        = messages.changeDetailJoiner || ' · ';
                        const changeDetails       = [];
                        const previousStock       = ( typeof changes.previous_stock === 'number' ) ? changes.previous_stock : null;
                        const newStock            = ( typeof changes.new_stock === 'number' ) ? changes.new_stock : null;
                        const previousPrice       = ( typeof changes.previous_price === 'number' ) ? changes.previous_price : null;
                        const newPrice            = ( typeof changes.new_price === 'number' ) ? changes.new_price : null;
                        const previousIdentifier  = changes.previous_identifier ? String( changes.previous_identifier ) : '';
                        const newIdentifier       = changes.new_identifier ? String( changes.new_identifier ) : '';

                        if ( previousStock !== null || newStock !== null ) {
                                changeDetails.push( {
                                        label:   messages.stockChangeLabel || 'Inventario sincronizado',
                                        value:   formatChangeValue( previousStock, newStock, separator, noValue ),
                                        changed: previousStock !== null && newStock !== null && previousStock !== newStock
                                } );
                        }

                        if ( previousPrice !== null || newPrice !== null ) {
                                changeDetails.push( {
                                        label:   messages.priceChangeLabel || 'Precio sincronizado',
                                        value:   formatChangeValue( previousPrice, newPrice, separator, noValue ),
                                        changed: previousPrice !== null && newPrice !== null && previousPrice !== newPrice
                                } );
                        }

                        if ( typeof changes.meta_updated !== 'undefined' ) {
                                changeDetails.push( {
                                        label:   messages.identifierLabel || 'Identificador de Contífico',
                                        value:   formatIdentifierValue( previousIdentifier, newIdentifier, separator, noIdentifier ),
                                        changed: previousIdentifier !== newIdentifier
                                } );
                        }

                        $container.removeClass( 'error success' ).empty();

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

                        if ( data.message ) {
                                $container.append( $( '<p />' ).addClass( 'woo-contifico-sync-result-message' ).text( data.message ) );
                        }

                        $container.append(
                                $( '<p />' )
                                        .addClass( 'woo-contifico-sync-result-summary' )
                                        .text( ( messages.changesLabel || 'Cambios detectados:' ) + ' ' + changeMessages.join( ' ' ) )
                        );

                        if ( changeDetails.length > 0 ) {
                                const changeSummaryText = changeDetails.map( function ( detail ) {
                                        return detail.label + ': ' + detail.value;
                                } ).join( detailJoiner );

                                $container.append(
                                        $( '<p />' )
                                                .addClass( 'woo-contifico-sync-result-detail-summary' )
                                                .text( detailSummaryLabel + ' ' + changeSummaryText )
                                );
                        }

                        const generalDetails = [];

                        if ( data.woocommerce_sku ) {
                                generalDetails.push( { label: messages.wooSkuLabel || 'SKU en WooCommerce:', value: data.woocommerce_sku } );
                        }

                        if ( data.contifico_sku ) {
                                generalDetails.push( { label: messages.contificoSkuLabel || 'SKU en Contífico:', value: data.contifico_sku } );
                        }

                        if ( data.contifico_id ) {
                                generalDetails.push( { label: messages.contificoIdLabel || 'ID de Contífico:', value: data.contifico_id } );
                        } else {
                                generalDetails.push( { label: messages.contificoIdLabel || 'ID de Contífico:', value: messages.noIdentifier || 'Sin identificador registrado.' } );
                        }

                        if ( typeof data.stock_quantity === 'number' ) {
                                generalDetails.push( { label: messages.stockLabel || 'Inventario disponible:', value: data.stock_quantity } );
                        }

                        if ( typeof data.price === 'number' ) {
                                generalDetails.push( { label: messages.priceLabel || 'Precio actual:', value: data.price } );
                        }

                        if ( generalDetails.length > 0 ) {
                                const $detailsList = $( '<ul />' ).addClass( 'woo-contifico-sync-result-list' );

                                generalDetails.forEach( function ( detail ) {
                                        const $item = $( '<li />' );
                                        $item.append( $( '<span />' ).addClass( 'woo-contifico-sync-detail-label' ).text( detail.label + ' ' ) );
                                        $item.append( document.createTextNode( detail.value ) );
                                        $detailsList.append( $item );
                                } );

                                $container.append( $detailsList );
                        }

                        if ( changeDetails.length > 0 ) {
                                $container.append(
                                        $( '<p />' )
                                                .addClass( 'woo-contifico-sync-result-heading' )
                                                .text( messages.changesHeading || 'Detalle de cambios' )
                                );

                                const $changeList = $( '<ul />' ).addClass( 'woo-contifico-sync-changes-list' );

                                changeDetails.forEach( function ( detail ) {
                                        const $item = $( '<li />' );
                                        if ( detail.changed ) {
                                                $item.addClass( 'woo-contifico-sync-change-updated' );
                                        }

                                        $item.append( $( '<span />' ).addClass( 'woo-contifico-sync-detail-label' ).text( detail.label + ': ' ) );
                                        $item.append( document.createTextNode( detail.value ) );
                                        $changeList.append( $item );
                                } );

                                $container.append( $changeList );
                        }

                        $container.addClass( 'success' ).show();
                }

                namespace.renderSingleSyncResult = renderSingleSyncResult;

                function renderGlobalSyncUpdates( updates ) {
                        const $section = $( '#' + pluginGlobals.plugin_name + '-settings-page .fetch-products' );

                        if ( ! $section.length ) {
                                return;
                        }

                        const $summary       = $section.find( '.sync-summary' );
                        const $heading       = $summary.find( '.sync-summary-heading' );
                        const $emptyMessage  = $summary.find( '.sync-summary-empty' );
                        const $updatesList   = $summary.find( '.sync-summary-list' );
                        const headingText    = messages.globalSyncHeading || ( $heading.length ? $heading.text() : '' ) || 'Resumen de actualizaciones';
                        const emptyStateText = messages.globalSyncEmpty || ( $emptyMessage.length ? $emptyMessage.text() : '' ) || 'No se registraron cambios durante la sincronización.';

                        if ( $heading.length ) {
                                $heading.text( headingText );
                        }

                        if ( ! Array.isArray( updates ) || updates.length === 0 ) {
                                if ( $updatesList.length ) {
                                        $updatesList.empty().hide();
                                }

                                if ( $emptyMessage.length ) {
                                        $emptyMessage.text( emptyStateText ).show();
                                }

                                if ( $summary.length ) {
                                        $summary.attr( 'hidden', 'hidden' );
                                }

                                return;
                        }

                        if ( $summary.length ) {
                                $summary.removeAttr( 'hidden' );
                        }

                        if ( $emptyMessage.length ) {
                                $emptyMessage.hide();
                        }

                        if ( ! $updatesList.length ) {
                                return;
                        }

                        const separator      = messages.changeSeparator || '→';
                        const noValue        = messages.noValue || 'N/D';
                        const noIdentifier   = messages.noIdentifier || 'Sin identificador registrado.';
                        const stockLabel     = messages.stockChangeLabel || 'Inventario sincronizado';
                        const priceLabel     = messages.priceChangeLabel || 'Precio sincronizado';
                        const identifierLabel = messages.identifierLabel || 'Identificador de Contífico';
                        const metaJoiner     = messages.changeDetailJoiner || ' · ';
                        const wooSkuLabel    = messages.wooSkuLabel || 'SKU en WooCommerce:';
                        const contificoIdLabel = messages.contificoIdLabel || 'ID de Contífico:';

                        $updatesList.empty().show();

                        updates.forEach( function ( entry ) {
                                const productName = entry && entry.product_name ? String( entry.product_name ) : '';
                                const sku = entry && entry.sku ? String( entry.sku ) : '';
                                const contificoId = entry && entry.contifico_id ? String( entry.contifico_id ) : '';
                                const changes = entry && entry.changes ? entry.changes : {};
                                const changeLines = [];
                                const metaParts = [];

                                if ( changes && changes.stock ) {
                                        const stockChange      = changes.stock;
                                        const previousStock    = ( typeof stockChange.previous === 'number' ) ? stockChange.previous : null;
                                        const newStock         = ( typeof stockChange.current === 'number' ) ? stockChange.current : null;
                                        const stockDescription = formatChangeValue( previousStock, newStock, separator, noValue );

                                        changeLines.push( stockLabel + ': ' + stockDescription );

                                        if ( stockChange.outofstock ) {
                                                changeLines.push( messages.outOfStock || 'Producto sin stock.' );
                                        }
                                }

                                if ( changes && changes.price ) {
                                        const priceChange      = changes.price;
                                        const previousPrice    = ( typeof priceChange.previous === 'number' ) ? priceChange.previous : null;
                                        const newPrice         = ( typeof priceChange.current === 'number' ) ? priceChange.current : null;
                                        const priceDescription = formatChangeValue( previousPrice, newPrice, separator, noValue );

                                        changeLines.push( priceLabel + ': ' + priceDescription );
                                }

                                if ( changes && changes.identifier ) {
                                        const identifierChange   = changes.identifier;
                                        const previousIdentifier = identifierChange.previous ? String( identifierChange.previous ) : '';
                                        const newIdentifier      = identifierChange.current ? String( identifierChange.current ) : '';
                                        const identifierText     = formatIdentifierValue( previousIdentifier, newIdentifier, separator, noIdentifier );

                                        changeLines.push( identifierLabel + ': ' + identifierText );
                                }

                                const $item = $( '<li />' ).addClass( 'sync-summary-item' );

                                if ( productName ) {
                                        $item.append( $( '<span />' ).addClass( 'sync-summary-title' ).text( productName ) );
                                } else if ( sku || contificoId ) {
                                        $item.append( $( '<span />' ).addClass( 'sync-summary-title' ).text( sku || contificoId ) );
                                }

                                if ( sku ) {
                                        metaParts.push( wooSkuLabel + ' ' + sku );
                                }

                                if ( contificoId ) {
                                        metaParts.push( contificoIdLabel + ' ' + contificoId );
                                }

                                if ( metaParts.length > 0 ) {
                                        $item.append( $( '<p />' ).addClass( 'sync-summary-meta' ).text( metaParts.join( metaJoiner ) ) );
                                }

                                if ( changeLines.length > 0 ) {
                                        const $changeList = $( '<ul />' ).addClass( 'sync-summary-change-list' );

                                        changeLines.forEach( function ( line ) {
                                                $changeList.append( $( '<li />' ).text( line ) );
                                        } );

                                        $item.append( $changeList );
                                }

                                $updatesList.append( $item );
                        } );
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
                        renderGlobalSyncUpdates( [] );

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

                        if ( ! ajaxEndpoint ) {
                                return;
                        }

                        $.ajax({
                                type: 'post',
                                url: ajaxEndpoint,
                                data: data,
                                success: function (response) {

                                        const $section = $( '#' + pluginGlobals.plugin_name + '-settings-page .fetch-products' );

                                        if ( response && typeof response === 'object' ) {
                                                const updates = Array.isArray( response.updates ) ? response.updates : [];

                                                renderGlobalSyncUpdates( updates );

                                                if ( typeof response.fetched !== 'undefined' ) {
                                                        $section.find( '.result .fetched' ).html( response.fetched );
                                                }

                                                if ( typeof response.found !== 'undefined' ) {
                                                        $section.find( '.result .found' ).html( response.found );
                                                }

                                                if ( typeof response.updated !== 'undefined' ) {
                                                        $section.find( '.result .updated' ).html( response.updated );
                                                }

                                                if ( typeof response.outofstock !== 'undefined' ) {
                                                        $section.find( '.result .outofstock' ).html( response.outofstock );
                                                }
                                        }

                                        if ( response && response.step && response.step !== 'done' ) {
                                                batch_processing( response.step );
                                                return;
                                        }

                                        $section.find( 'img' ).fadeOut();
                                },
                                error: function (response) {
                                        $( '#' + pluginGlobals.plugin_name + '-settings-page .fetch-products .result' ).html( response.responseText );
                                        renderGlobalSyncUpdates( [] );
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

                                if ( syncingMessage ) {
                                        $result.text( syncingMessage ).show();
                                }

                                if ( ! ajaxEndpoint ) {
                                        if ( genericError ) {
                                                $result.addClass( 'error' ).text( genericError ).show();
                                        }

                                        return;
                                }

                                $button.prop( 'disabled', true );
                                $spinner.fadeIn();

                                $.ajax( {
                                        type: 'post',
                                        url: ajaxEndpoint,
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

                function updateProductIdentifierDisplay( $field, contificoId, missingIdentifier ) {
                        const $value   = $field.find( '.woo-contifico-product-id-value' );
                        const $missing = $field.find( '.woo-contifico-product-id-missing' );

                        if ( contificoId ) {
                                const $newNode = $( '<span />', {
                                        'class': 'woo-contifico-product-id-value',
                                        text:   contificoId
                                } );

                                if ( $value.length ) {
                                        $value.replaceWith( $newNode );
                                } else if ( $missing.length ) {
                                        $missing.replaceWith( $newNode );
                                } else {
                                        $field.find( '.form-field' ).append( $newNode );
                                }

                                return;
                        }

                        if ( ! missingIdentifier ) {
                                return;
                        }

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

                namespace.updateProductIdentifierDisplay = updateProductIdentifierDisplay;

                // Load tax info when looking for customer in order backend
                $('#order_data .wc-customer-user select.wc-customer-search').change(function(){

			//Call Ajax
			/** @param {{get_customer_details_nonce}} woocommerce_admin_meta_boxes */
                        if ( ! ajaxEndpoint ) {
                                return;
                        }

                        $.ajax({
                                type: "POST",
                                url: ajaxEndpoint,
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
