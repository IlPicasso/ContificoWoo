/* global jQuery, wooContificoProductStock */
(function( $ ) {
        'use strict';

        const config = window.wooContificoProductStock || {};

        if ( ! config || ! config.ajaxUrl || ! config.nonce ) {
                return;
        }

        $( function() {
                if ( config.manageStock === false ) {
                        return;
                }

                const selectors = config.selectors || {};
                const $stockNode = selectors.stockNode ? $( selectors.stockNode ) : $( '.summary .stock' );

                if ( ! $stockNode.length ) {
                        return;
                }

                const requestData = {
                        action:   'woo_contifico_sync_single_product',
                        security: config.nonce
                };

                if ( config.productId ) {
                        requestData.product_id = config.productId;
                }

                if ( config.sku ) {
                        requestData.sku = config.sku;
                }

                if ( ! requestData.product_id && ! requestData.sku ) {
                        return;
                }

                const messages = config.messages || {};

                if ( messages.syncing ) {
                        $stockNode
                                .removeClass( 'woo-contifico-stock-error' )
                                .addClass( 'woo-contifico-stock-updating' )
                                .text( messages.syncing );
                }

                $.ajax( {
                        type: 'post',
                        url: config.ajaxUrl,
                        data: requestData,
                        dataType: 'json'
                } ).done( function( response ) {
                        if ( response && response.success && response.data ) {
                                const data = response.data;

                                if ( typeof data.stock_quantity === 'number' ) {
                                        updateStockNode( data.stock_quantity );
                                }
                        } else {
                                const errorMessage = response && response.data && response.data.message
                                        ? response.data.message
                                        : '';
                                renderError( errorMessage );
                        }
                } ).fail( function() {
                        renderError();
                } );

                function updateStockNode( quantity ) {
                        const normalizedQuantity = parseInt( quantity, 10 );

                        if ( Number.isNaN( normalizedQuantity ) ) {
                                return;
                        }

                        if ( normalizedQuantity <= 0 ) {
                                $stockNode
                                        .removeClass( 'in-stock woo-contifico-stock-updating' )
                                        .addClass( 'out-of-stock' )
                                        .text( messages.outOfStock || messages.error || '' );

                                return;
                        }

                        const formattedMessage = formatInStockMessage( normalizedQuantity );

                        $stockNode
                                .removeClass( 'out-of-stock woo-contifico-stock-error woo-contifico-stock-updating' )
                                .addClass( 'in-stock' );

                        if ( formattedMessage ) {
                                $stockNode.text( formattedMessage );
                        }
                }

                function formatInStockMessage( quantity ) {
                        const template = messages.inStockWithQuantity || messages.inStock || '';

                        if ( template.indexOf( '%d' ) !== -1 ) {
                                return template.replace( '%d', quantity );
                        }

                        if ( template ) {
                                return template + ' ' + quantity;
                        }

                        return String( quantity );
                }

                function renderError( customMessage ) {
                        const message = customMessage || messages.error;

                        if ( ! message ) {
                                return;
                        }

                        $stockNode
                                .removeClass( 'woo-contifico-stock-updating' )
                                .addClass( 'woo-contifico-stock-error' )
                                .text( message );
                }
        } );
})( jQuery );
