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

                // Manual synchronization controls
                ( function registerManualSyncControls() {
                        const selector = '#' + pluginGlobals.plugin_name + '-settings-page .manual-sync-section';
                        const $sections = $( selector );

                        if ( ! $sections.length ) {
                                return;
                        }

                        const pollingInterval = Math.max( 3000, parseInt( pluginGlobals.manualSyncPollingInterval || 5000, 10 ) || 5000 );
                        const activeStatuses  = [ 'queued', 'running', 'cancelling' ];

                        $sections.each( function () {
                                const $section      = $( this );
                                const $startButton  = $section.find( '.manual-sync-start' );
                                const $cancelButton = $section.find( '.manual-sync-cancel' );
                                const $spinner      = $section.find( '.manual-sync-spinner' );
                                const $status       = $section.find( '.manual-sync-status-message' );
                                const $resultBox    = $section.find( '.result' );
                                const startAction   = String( $section.data( 'start-action' ) || '' );
                                const statusAction  = String( $section.data( 'status-action' ) || '' );
                                const cancelAction  = String( $section.data( 'cancel-action' ) || '' );
                                const historyUrl    = String( $section.data( 'history-url' ) || '' );
                                const initialState  = $section.data( 'initial-state' ) || null;
                                const $counts       = {
                                        fetched: $resultBox.find( '.fetched' ),
                                        found: $resultBox.find( '.found' ),
                                        updated: $resultBox.find( '.updated' ),
                                        outofstock: $resultBox.find( '.outofstock' )
                                };

                                let pollingTimer = null;
                                let lastState = null;

                                function setSpinner( isActive ) {
                                        if ( ! $spinner.length ) {
                                                return;
                                        }

                                        if ( isActive ) {
                                                $spinner.addClass( 'is-active' );
                                        } else {
                                                $spinner.removeClass( 'is-active' );
                                        }
                                }

                                function setLoading( isLoading ) {
                                        if ( $startButton.length ) {
                                                $startButton.prop( 'disabled', isLoading || isStatusActive( lastState ) );
                                        }

                                        if ( $cancelButton.length ) {
                                                $cancelButton.prop( 'disabled', isLoading );
                                        }

                                        setSpinner( isLoading );
                                }

                                function isStatusActive( state ) {
                                        const status = state && state.status ? String( state.status ) : 'idle';

                                        return activeStatuses.indexOf( status ) !== -1;
                                }

                                function stopPolling() {
                                        if ( pollingTimer ) {
                                                window.clearInterval( pollingTimer );
                                                pollingTimer = null;
                                        }
                                }

                                function schedulePolling() {
                                        stopPolling();

                                        if ( ! statusAction ) {
                                                return;
                                        }

                                        pollingTimer = window.setInterval( function () {
                                                fetchStatus( { silent: true } );
                                        }, pollingInterval );
                                }

                                function updateCounts( progress ) {
                                        const fetched    = progress && typeof progress.fetched !== 'undefined' ? progress.fetched : 0;
                                        const found      = progress && typeof progress.found !== 'undefined' ? progress.found : 0;
                                        const updated    = progress && typeof progress.updated !== 'undefined' ? progress.updated : 0;
                                        const outofstock = progress && typeof progress.outofstock !== 'undefined' ? progress.outofstock : 0;

                                        if ( $counts.fetched.length ) {
                                                $counts.fetched.text( fetched );
                                        }

                                        if ( $counts.found.length ) {
                                                $counts.found.text( found );
                                        }

                                        if ( $counts.updated.length ) {
                                                $counts.updated.text( updated );
                                        }

                                        if ( $counts.outofstock.length ) {
                                                $counts.outofstock.text( outofstock );
                                        }

                                        const shouldShow = isStatusActive( lastState ) || fetched || found || updated || outofstock;

                                        if ( $resultBox.length ) {
                                                $resultBox.prop( 'hidden', ! shouldShow );
                                        }
                                }

                                function resolveStatusMessage( state ) {
                                        const status = state && state.status ? String( state.status ) : 'idle';
                                        const customMessage = state && state.message ? String( state.message ) : '';

                                        if ( customMessage ) {
                                                return customMessage;
                                        }

                                        switch ( status ) {
                                                case 'queued':
                                                        return messages.manualSyncQueued || '';
                                                case 'running':
                                                        return messages.manualSyncRunning || '';
                                                case 'cancelling':
                                                        return messages.manualSyncCancelling || '';
                                                case 'cancelled':
                                                        return messages.manualSyncCancelled || '';
                                                case 'completed':
                                                        return messages.manualSyncCompleted || '';
                                                case 'failed':
                                                        return messages.manualSyncFailed || '';
                                                case 'idle':
                                                        return messages.manualSyncIdle || '';
                                                default:
                                                        return messages.manualSyncStatusUnknown || '';
                                        }
                                }

                                function renderStatusMessage( state ) {
                                        if ( ! $status.length ) {
                                                return;
                                        }

                                        const message = resolveStatusMessage( state );
                                        const lastUpdated = state && state.last_updated ? String( state.last_updated ) : '';
                                        const status = state && state.status ? String( state.status ) : 'idle';

                                        $status.removeClass( 'empty' ).empty();

                                        if ( message ) {
                                                $status.text( message );
                                        } else {
                                                $status.addClass( 'empty' );
                                        }

                                        if ( lastUpdated ) {
                                                const lastUpdatedTemplate = messages.manualSyncLastUpdated || '';

                                                if ( lastUpdatedTemplate ) {
                                                        const text = lastUpdatedTemplate.replace( '%s', lastUpdated );

                                                        if ( text ) {
                                                                if ( $status.text() ) {
                                                                        $status.append( ' · ' );
                                                                }

                                                                $status.append( $( '<span />' ).addClass( 'manual-sync-last-updated' ).text( text ) );
                                                        }
                                                }
                                        }

                                        if ( status === 'completed' && historyUrl ) {
                                                const linkLabel = messages.manualSyncHistoryLinkLabel || '';

                                                if ( linkLabel ) {
                                                        const $link = $( '<a />', {
                                                                href: historyUrl,
                                                                text: linkLabel
                                                        } );

                                                        if ( $status.text() ) {
                                                                $status.append( ' ' );
                                                        }

                                                        $status.append( $link );
                                                } else if ( messages.manualSyncViewHistory ) {
                                                        if ( $status.text() ) {
                                                                $status.append( ' ' + messages.manualSyncViewHistory );
                                                        } else {
                                                                $status.text( messages.manualSyncViewHistory );
                                                        }
                                                }
                                        }
                                }

                                function applyState( state ) {
                                        lastState = state || null;

                                        const progress = state && state.progress && typeof state.progress === 'object' ? state.progress : {};
                                        const updates  = Array.isArray( progress.updates ) ? progress.updates : [];
                                        const status   = state && state.status ? String( state.status ) : 'idle';
                                        const active   = isStatusActive( state );

                                        if ( active ) {
                                                schedulePolling();
                                        } else {
                                                stopPolling();
                                        }

                                        updateCounts( progress );
                                        renderGlobalSyncUpdates( updates );
                                        renderStatusMessage( state );

                                        if ( $startButton.length ) {
                                                $startButton.prop( 'disabled', active );
                                        }

                                        if ( $cancelButton.length ) {
                                                if ( active || status === 'cancelling' ) {
                                                        $cancelButton.show();
                                                        $cancelButton.prop( 'disabled', status === 'cancelling' );
                                                } else {
                                                        $cancelButton.hide().prop( 'disabled', false );
                                                }
                                        }
                                }

                                function extractErrorMessage( xhr, fallback ) {
                                        if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
                                                return xhr.responseJSON.data.message;
                                        }

                                        if ( xhr && xhr.responseText ) {
                                                return xhr.responseText;
                                        }

                                        return fallback || messages.manualSyncError || '';
                                }

                                function handleError( message ) {
                                        if ( $status.length ) {
                                                $status.removeClass( 'empty' ).text( message || messages.manualSyncError || '' );
                                        }
                                }

                                function fetchStatus( options ) {
                                        options = options || {};

                                        if ( ! ajaxEndpoint || ! statusAction ) {
                                                return;
                                        }

                                        $.ajax( {
                                                type: 'post',
                                                url: ajaxEndpoint,
                                                data: {
                                                        action:   statusAction,
                                                        security: pluginGlobals.woo_nonce
                                                }
                                        } ).done( function ( response ) {
                                                if ( response && response.success && response.data && response.data.state ) {
                                                        applyState( response.data.state );
                                                } else if ( ! options.silent ) {
                                                        handleError( messages.manualSyncError || '' );
                                                }
                                        } ).fail( function () {
                                                if ( ! options.silent ) {
                                                        handleError( messages.manualSyncError || '' );
                                                }
                                        } );
                                }

                                $startButton.on( 'click', function ( event ) {
                                        event.preventDefault();

                                        if ( ! ajaxEndpoint || ! startAction ) {
                                                return;
                                        }

                                        setLoading( true );

                                        if ( $status.length && messages.manualSyncStarting ) {
                                                $status.removeClass( 'empty' ).text( messages.manualSyncStarting );
                                        }

                                        $.ajax( {
                                                type: 'post',
                                                url: ajaxEndpoint,
                                                data: {
                                                        action:   startAction,
                                                        security: pluginGlobals.woo_nonce
                                                }
                                        } ).done( function ( response ) {
                                                if ( response && response.success && response.data && response.data.state ) {
                                                        applyState( response.data.state );
                                                } else {
                                                        handleError( messages.manualSyncError || '' );
                                                }
                                        } ).fail( function ( xhr ) {
                                                handleError( extractErrorMessage( xhr, messages.manualSyncError ) );
                                        } ).always( function () {
                                                setLoading( false );
                                                fetchStatus( { silent: true } );
                                        } );
                                } );

                                $cancelButton.hide().on( 'click', function ( event ) {
                                        event.preventDefault();

                                        if ( ! ajaxEndpoint || ! cancelAction ) {
                                                return;
                                        }

                                        setLoading( true );

                                        if ( $status.length && messages.manualSyncCanceling ) {
                                                $status.removeClass( 'empty' ).text( messages.manualSyncCanceling );
                                        }

                                        $.ajax( {
                                                type: 'post',
                                                url: ajaxEndpoint,
                                                data: {
                                                        action:   cancelAction,
                                                        security: pluginGlobals.woo_nonce
                                                }
                                        } ).done( function ( response ) {
                                                if ( response && response.success && response.data && response.data.state ) {
                                                        applyState( response.data.state );
                                                } else {
                                                        handleError( messages.manualSyncError || '' );
                                                }
                                        } ).fail( function ( xhr ) {
                                                handleError( extractErrorMessage( xhr, messages.manualSyncError ) );
                                        } ).always( function () {
                                                setLoading( false );
                                                fetchStatus( { silent: true } );
                                        } );
                                } );

                                if ( initialState && typeof initialState === 'object' ) {
                                        applyState( initialState );
                                }

                                fetchStatus( { silent: !! initialState } );
                        } );
                } )();

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
