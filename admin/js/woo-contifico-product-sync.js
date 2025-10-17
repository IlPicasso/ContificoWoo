/* global jQuery, woo_contifico_globals, ajaxurl */
(function( $ ) {
        'use strict';

        $( function() {
                const pluginGlobals = ( typeof woo_contifico_globals !== 'undefined' ) ? woo_contifico_globals : {};
                const namespace     = window.wooContificoAdmin || {};
                const ajaxEndpoint  = pluginGlobals.ajaxUrl
                        || ( namespace.pluginGlobals ? namespace.pluginGlobals.ajaxUrl : '' )
                        || ( typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '' );
                const nonce         = pluginGlobals.woo_nonce || ( namespace.pluginGlobals ? namespace.pluginGlobals.woo_nonce : '' );
                const messages      = $.extend( {}, namespace.messages || {}, pluginGlobals.messages || {} );

                const syncingMessage         = messages.syncing || '';
                const genericErrorMessage    = messages.genericError || '';
                const missingSkuMessage      = messages.missingSku || genericErrorMessage;
                const fallbackMissingId      = messages.missingIdentifier || messages.noIdentifier || '';
                const defaultReloadDelay     = 2000;
                const renderResult           = ( typeof namespace.renderSingleSyncResult === 'function' )
                        ? namespace.renderSingleSyncResult
                        : function( $container, data ) {
                                $container.removeClass( 'error success' ).empty();

                                if ( data && data.message ) {
                                        $container.text( data.message );
                                }

                                $container.addClass( 'success' ).show();
                        };
                const updateIdentifierDisplay = ( typeof namespace.updateProductIdentifierDisplay === 'function' )
                        ? namespace.updateProductIdentifierDisplay
                        : function( $field, contificoId, missingIdentifier ) {
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

                                const missingText = missingIdentifier || fallbackMissingId;

                                if ( ! missingText ) {
                                        return;
                                }

                                if ( $value.length ) {
                                        $value.replaceWith( $( '<span />', {
                                                'class': 'woo-contifico-product-id-missing',
                                                text:   missingText
                                        } ) );
                                } else if ( ! $missing.length ) {
                                        $field.find( '.form-field' ).append( $( '<span />', {
                                                'class': 'woo-contifico-product-id-missing',
                                                text:   missingText
                                        } ) );
                                }
                        };

                $( document ).on( 'click', '.woo-contifico-sync-product-button', function( event ) {
                        event.preventDefault();

                        const $button = $( this );
                        const $field  = $button.closest( '.woo-contifico-product-id-field' );

                        if ( ! $field.length ) {
                                return;
                        }

                        const $result           = $field.find( '.woo-contifico-sync-result' );
                        const $spinner          = $field.find( '.woo-contifico-sync-spinner' );
                        const $skuInput         = $( '#_sku' );
                        const reloadAttribute   = String( $field.attr( 'data-reload-on-success' ) || '' ).toLowerCase();
                        const reloadDelayAttr   = parseInt( String( $field.attr( 'data-reload-delay' ) || '' ), 10 );
                        const reloadDelay       = Number.isNaN( reloadDelayAttr ) || reloadDelayAttr < 0 ? defaultReloadDelay : reloadDelayAttr;
                        const reloadMessageAttr = $field.attr( 'data-reload-message' );
                        const reloadMessage     = reloadMessageAttr ? String( reloadMessageAttr ) : ( messages.pageReloadPending || '' );
                        const reloadOnSuccess   = [ '1', 'true', 'yes', 'on' ].indexOf( reloadAttribute ) !== -1;
                        const missingIdentifier = $field.data( 'missing-identifier' )
                                || $field.attr( 'data-missing-identifier' )
                                || fallbackMissingId;
                        const genericError      = $field.data( 'generic-error' )
                                || $field.attr( 'data-generic-error' )
                                || genericErrorMessage;
                        const productId         = parseInt( $field.attr( 'data-product-id' ) || $field.data( 'product-id' ) || '0', 10 ) || 0;
                        const skuFromAttr       = $.trim( String( $field.attr( 'data-product-sku' ) || $field.data( 'product-sku' ) || '' ) );
                        const skuFromInput      = $.trim( $skuInput.length ? ( $skuInput.val() || '' ) : '' );
                        const sku               = skuFromInput || skuFromAttr;

                        $result.removeClass( 'error success' ).empty();

                        if ( ! ajaxEndpoint ) {
                                if ( genericError ) {
                                        $result.addClass( 'error' ).text( genericError ).show();
                                }

                                return;
                        }

                        const requestData = {
                                action:   'woo_contifico_sync_single_product',
                                security: nonce
                        };

                        if ( productId > 0 ) {
                                requestData.product_id = productId;
                        }

                        if ( sku ) {
                                requestData.sku = sku;
                        }

                        if ( ! requestData.product_id && ! sku ) {
                                if ( missingSkuMessage ) {
                                        $result.addClass( 'error' ).text( missingSkuMessage ).show();
                                }

                                return;
                        }

                        if ( syncingMessage ) {
                                $result.text( syncingMessage ).show();
                        } else {
                                $result.hide();
                        }

                        $button.prop( 'disabled', true );
                        $spinner.addClass( 'is-active' );

                        $.ajax( {
                                type: 'post',
                                url: ajaxEndpoint,
                                data: requestData
                        } ).done( function( response ) {
                                if ( response && response.success && response.data ) {
                                        const data = response.data;

                                        renderResult( $result, data );

                                        if ( data.woocommerce_sku ) {
                                                $field.attr( 'data-product-sku', data.woocommerce_sku );
                                                $field.data( 'product-sku', data.woocommerce_sku );

                                                if ( $skuInput.length && $.trim( $skuInput.val() || '' ) === '' ) {
                                                        $skuInput.val( data.woocommerce_sku );
                                                }
                                        }

                                        updateIdentifierDisplay( $field, data.contifico_id || '', missingIdentifier );

                                        if ( reloadOnSuccess ) {
                                                if ( reloadMessage ) {
                                                        $result.append( $( '<p />' )
                                                                .addClass( 'woo-contifico-sync-result-reloading' )
                                                                .text( reloadMessage )
                                                        );
                                                }

                                                window.setTimeout( function() {
                                                        window.location.reload();
                                                }, reloadDelay );
                                        }
                                } else {
                                        const message = ( response && response.data && response.data.message ) ? response.data.message : genericError;

                                        if ( message ) {
                                                $result.addClass( 'error' ).text( message ).show();
                                        }
                                }
                        } ).fail( function( xhr ) {
                                let message = genericError;

                                if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
                                        message = xhr.responseJSON.data.message;
                                } else if ( xhr && xhr.responseText ) {
                                        message = xhr.responseText;
                                }

                                if ( message ) {
                                        $result.addClass( 'error' ).text( message ).show();
                                }
                        } ).always( function() {
                                $button.prop( 'disabled', false );
                                $spinner.removeClass( 'is-active' );
                        } );
                } );
        } );
})( jQuery );
