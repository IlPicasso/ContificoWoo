/* global wooContificoProductStock */
(() => {
  'use strict';

  const config = window.wooContificoProductStock || {};
  const $ = window.jQuery || null;

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
    const stockSelector = selectors.stockNode || '.summary .stock';
    const messages = config.messages || {};

    const resolveStockNode = () => document.querySelector( stockSelector ) || document.querySelector( '.summary .stock' );

    const buildRequestData = ( productId, sku ) => {
      const requestData = new URLSearchParams();
      requestData.append( 'action', 'woo_contifico_sync_single_product' );
      requestData.append( 'security', config.nonce );

      if ( productId ) {
        requestData.append( 'product_id', productId );
      }

      if ( sku ) {
        requestData.append( 'sku', sku );
      }

      return requestData;
    };

    const refreshStock = ( productId, sku ) => {
      if ( ! productId && ! sku ) {
        return;
      }

      const requestData = buildRequestData( productId, sku );

      const stockNode = resolveStockNode();

      if ( stockNode && messages.syncing ) {
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
    };

    const updateStockNode = ( quantity ) => {
      const normalizedQuantity = parseInt( quantity, 10 );
      const stockNode = resolveStockNode();

      if ( ! stockNode || Number.isNaN( normalizedQuantity ) ) {
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
    };

    const formatInStockMessage = ( quantity ) => {
      const template = messages.inStockWithQuantity || messages.inStock || '';

      if ( template.indexOf( '%d' ) !== -1 ) {
        return template.replace( '%d', quantity );
      }

      if ( template ) {
        return `${ template } ${ quantity }`;
      }

      return String( quantity );
    };

    const renderError = ( customMessage ) => {
      const stockNode = resolveStockNode();
      const message = customMessage || messages.error;

      if ( ! stockNode || ! message ) {
        return;
      }

      stockNode.classList.remove( 'woo-contifico-stock-updating' );
      stockNode.classList.add( 'woo-contifico-stock-error' );
      stockNode.textContent = message;
    };

    const variationForm = document.querySelector( 'form.variations_form' );

    if ( variationForm ) {
      const variationIdInput = variationForm.querySelector( 'input.variation_id' );
      const variationDataRaw = variationForm.getAttribute( 'data-product_variations' );
      let variationData = [];

      if ( typeof variationDataRaw === 'string' && variationDataRaw.trim() !== '' ) {
        try {
          variationData = JSON.parse( variationDataRaw );
        } catch ( e ) {
          variationData = [];
        }
      }

      const resolveVariationSku = ( variationId ) => {
        if ( ! variationId || ! Array.isArray( variationData ) ) {
          return '';
        }

        const match = variationData.find( ( item ) => {
          const id = parseInt( item.variation_id || item.variationId, 10 );

          return id === variationId;
        } );

        if ( match && match.sku ) {
          return String( match.sku );
        }

        return '';
      };

      const handleVariationRefresh = () => {
        const variationId = variationIdInput ? parseInt( variationIdInput.value, 10 ) : 0;

        if ( variationId > 0 ) {
          const variationSku = resolveVariationSku( variationId ) || config.sku || '';
          refreshStock( variationId, variationSku );

          return;
        }

        refreshStock( config.productId, config.sku );
      };

      if ( $ && typeof $.fn === 'object' && typeof $( variationForm ).on === 'function' ) {
        $( variationForm ).on(
          'found_variation.wc-contifico hide_variation.wc-contifico reset_data.wc-contifico',
          handleVariationRefresh
        );
      } else {
        variationForm.addEventListener( 'found_variation', handleVariationRefresh );
        variationForm.addEventListener( 'hide_variation', handleVariationRefresh );
        variationForm.addEventListener( 'reset_data', handleVariationRefresh );
      }

      if ( variationIdInput ) {
        variationIdInput.addEventListener( 'change', handleVariationRefresh );

        const observer = new MutationObserver( ( mutations ) => {
          mutations.forEach( ( mutation ) => {
            if ( mutation.type === 'attributes' || mutation.type === 'characterData' ) {
              handleVariationRefresh();
            }
          } );
        } );

        observer.observe( variationIdInput, {
          attributes: true,
          attributeFilter: [ 'value' ],
          characterData: true,
          subtree: false
        } );
      }
    }

    refreshStock( config.productId, config.sku );
  } );
})();
