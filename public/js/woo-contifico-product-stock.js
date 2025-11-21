/* global wooContificoProductStock */
(() => {
        'use strict';

        const config = window.wooContificoProductStock || {};

        if ( ! config || ! config.ajaxUrl || ! config.nonce ) {
                return;
        }

        const ready = ( callback ) => {
                if ( document.readyState === 'loading' ) {
                        document.addEventListener( 'DOMContentLoaded', callback );
                } else {
                        callback();
                }
        };

        ready( () => {
                if ( config.manageStock === false ) {
                        return;
                }

                const selectors = config.selectors || {};
                const stockNode = selectors.stockNode
                        ? document.querySelector( selectors.stockNode )
                        : document.querySelector( '.summary .stock' );

                if ( ! stockNode ) {
                        return;
                }

                const requestData = new URLSearchParams();
                requestData.append( 'action', 'woo_contifico_sync_single_product' );
                requestData.append( 'security', config.nonce );

                if ( config.productId ) {
                        requestData.append( 'product_id', config.productId );
                }

                if ( config.sku ) {
                        requestData.append( 'sku', config.sku );
                }

                if ( ! requestData.has( 'product_id' ) && ! requestData.has( 'sku' ) ) {
                        return;
                }

                const messages = config.messages || {};

                if ( messages.syncing ) {
                        stockNode.classList.remove( 'woo-contifico-stock-error' );
                        stockNode.classList.add( 'woo-contifico-stock-updating' );
                        stockNode.textContent = messages.syncing;
                }

                fetch( config.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: requestData.toString()
                } )
                        .then( ( response ) => response.json() )
                        .then( ( response ) => {
                                if ( response && response.success && response.data ) {
                                        const data = response.data;

                                        if ( typeof data.stock_quantity === 'number' ) {
                                                updateStockNode( data.stock_quantity );
                                        }

                                        return;
                                }

                                const errorMessage =
                                        response && response.data && response.data.message
                                                ? response.data.message
                                                : '';

                                renderError( errorMessage );
                        } )
                        .catch( () => {
                                renderError();
                        } );

                function updateStockNode( quantity ) {
                        const normalizedQuantity = parseInt( quantity, 10 );

                        if ( Number.isNaN( normalizedQuantity ) ) {
                                return;
                        }

                        if ( normalizedQuantity <= 0 ) {
                                stockNode.classList.remove( 'in-stock', 'woo-contifico-stock-updating' );
                                stockNode.classList.add( 'out-of-stock' );
                                stockNode.textContent = messages.outOfStock || messages.error || '';

                                return;
                        }

                        const formattedMessage = formatInStockMessage( normalizedQuantity );

                        stockNode.classList.remove( 'out-of-stock', 'woo-contifico-stock-error', 'woo-contifico-stock-updating' );
                        stockNode.classList.add( 'in-stock' );

                        if ( formattedMessage ) {
                                stockNode.textContent = formattedMessage;
                        }
                }

                function formatInStockMessage( quantity ) {
                        const template = messages.inStockWithQuantity || messages.inStock || '';

                        if ( template.indexOf( '%d' ) !== -1 ) {
                                return template.replace( '%d', quantity );
                        }

                        if ( template ) {
                                return `${ template } ${ quantity }`;
                        }

                        return String( quantity );
                }

                function renderError( customMessage ) {
                        const message = customMessage || messages.error;

                        if ( ! message ) {
                                return;
                        }

                        stockNode.classList.remove( 'woo-contifico-stock-updating' );
                        stockNode.classList.add( 'woo-contifico-stock-error' );
                        stockNode.textContent = message;
                }
        } );
})();
