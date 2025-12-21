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
    const locationSelector = selectors.locationNode || '.woo-contifico-location-stock';
    const messages = config.messages || {};
    const visibleWarehouseCodes = Array.isArray( config.visibleWarehouseCodes )
      ? config.visibleWarehouseCodes.map( ( code ) => String( code ).trim().toUpperCase() ).filter( ( code ) => code )
      : [];

    const resolveStockNode = () => document.querySelector( stockSelector ) || document.querySelector( '.summary .stock' );
    const resolveLocationNode = () => document.querySelector( locationSelector );

    const ensureLocationNode = () => {
      let locationNode = resolveLocationNode();

      if ( locationNode ) {
        return locationNode;
      }

      const stockNode = resolveStockNode();

      if ( ! stockNode ) {
        return null;
      }

      locationNode = document.createElement( 'div' );
      locationNode.classList.add( 'woo-contifico-location-stock' );

      if ( locationSelector && locationSelector.startsWith( '#' ) ) {
        locationNode.id = locationSelector.substring( 1 );
      } else if ( locationSelector && locationSelector.startsWith( '.' ) ) {
        locationNode.classList.add( locationSelector.substring( 1 ) );
      }

      stockNode.insertAdjacentElement( 'afterend', locationNode );

      return locationNode;
    };

    const filterLocationStockEntries = ( entries ) => {
      if ( ! Array.isArray( entries ) ) {
        return [];
      }

      if ( visibleWarehouseCodes.length === 0 ) {
        return [];
      }

      return entries.filter( ( entry ) => {
        if ( ! entry ) {
          return false;
        }

        const locationId = entry.location_id || entry.locationId || '';
        const locationLabel = entry.location_label || entry.locationLabel || '';
        const warehouseCode = entry.warehouse_code || entry.warehouseCode || '';

        const normalizedValues = [ locationId, locationLabel, warehouseCode ]
          .map( ( value ) => String( value ).trim().toUpperCase() )
          .filter( ( value ) => value );

        return normalizedValues.some( ( value ) => visibleWarehouseCodes.includes( value ) );
      } );
    };

    const renderLocationStock = ( entries ) => {
      const filteredEntries = filterLocationStockEntries( entries );
      const locationNode = ensureLocationNode();

      if ( ! locationNode ) {
        return;
      }

      if ( filteredEntries.length === 0 ) {
        locationNode.textContent = '';
        locationNode.hidden = true;
        return;
      }

      locationNode.hidden = false;
      locationNode.textContent = '';

      const title = messages.locationStockTitle || 'Existencias por bodega';
      const quantityLabel = messages.locationStockQuantity || 'Disponible';

      const titleElement = document.createElement( 'p' );
      titleElement.classList.add( 'woo-contifico-location-stock-title' );
      titleElement.textContent = title;
      locationNode.appendChild( titleElement );

      const table = document.createElement( 'table' );
      table.classList.add( 'woo-contifico-location-stock-table' );

      const thead = document.createElement( 'thead' );
      const headerRow = document.createElement( 'tr' );

      const locationHeader = document.createElement( 'th' );
      locationHeader.textContent = messages.locationStockLocation || 'Bodega';
      headerRow.appendChild( locationHeader );

      const quantityHeader = document.createElement( 'th' );
      quantityHeader.textContent = quantityLabel;
      headerRow.appendChild( quantityHeader );

      thead.appendChild( headerRow );
      table.appendChild( thead );

      const tbody = document.createElement( 'tbody' );

      filteredEntries.forEach( ( entry ) => {
        const row = document.createElement( 'tr' );

        const locationCell = document.createElement( 'td' );
        const label = entry.location_label || entry.locationLabel || entry.location_id || entry.locationId || '';
        locationCell.textContent = label;

        const quantityCell = document.createElement( 'td' );
        const quantity = entry.quantity ?? '';
        quantityCell.textContent = String( quantity );

        row.appendChild( locationCell );
        row.appendChild( quantityCell );
        tbody.appendChild( row );
      } );

      table.appendChild( tbody );
      locationNode.appendChild( table );
    };

    const resolveStockTotal = ( entries ) => {
      const filteredEntries = filterLocationStockEntries( entries );

      if ( filteredEntries.length === 0 ) {
        return null;
      }

      return filteredEntries.reduce( ( total, entry ) => {
        const quantity = entry && entry.quantity !== undefined && entry.quantity !== null
          ? parseFloat( entry.quantity )
          : 0;
        const safeQuantity = Number.isFinite( quantity ) ? quantity : 0;

        return total + safeQuantity;
      }, 0 );
    };

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

    let refreshSequence = 0;
    let refreshAbortController = null;

    const refreshStock = ( productId, sku ) => {
      if ( ! productId && ! sku ) {
        return;
      }

      refreshSequence += 1;
      const currentSequence = refreshSequence;

      const requestData = buildRequestData( productId, sku );
      const supportsAbortController = typeof AbortController !== 'undefined';

      if ( supportsAbortController ) {
        if ( refreshAbortController ) {
          refreshAbortController.abort();
        }

        refreshAbortController = new AbortController();
      }

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
        body: requestData.toString(),
        signal: refreshAbortController ? refreshAbortController.signal : undefined
      } )
        .then( ( response ) => response.json() )
        .then( ( response ) => {
          if ( currentSequence !== refreshSequence ) {
            return;
          }

          if ( response && response.success && response.data ) {
            const data = response.data;

            if ( Array.isArray( data.location_stock ) ) {
              renderLocationStock( data.location_stock );
            }

            const locationTotal = resolveStockTotal( data.location_stock );
            if ( locationTotal !== null ) {
              updateStockNode( locationTotal );
              return;
            }

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
        .catch( ( error ) => {
          if ( error && error.name === 'AbortError' ) {
            return;
          }

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
