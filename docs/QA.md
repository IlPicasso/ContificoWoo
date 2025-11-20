# Guía de verificación manual

Esta guía resume los pasos mínimos para validar compatibilidad con WooCommerce y las variantes conocidas de MultiLoca Lite/premium.

## Preparación del entorno
- WordPress 6.5+ con WooCommerce actualizado a la versión declarada en el encabezado del plugin (actualmente 8.7).
- Instalar y activar MultiLoca Lite desde WordPress.org y, si se dispone, la variante premium distribuida por Techspawn para validar ambos flujos.
- Asegurar que existen ubicaciones configuradas en MultiLoca y al menos un producto simple y uno variable con gestión de inventario habilitada.

## Detección automática y activación manual
1. Abrir **WooCommerce → Contífico → Integración** después de activar Woo Contífico y MultiLoca.
2. Confirmar que la sección **Compatibilidad MultiLoca** aparece automáticamente cuando MultiLoca registra sus taxonomías (`multiloca_location` o `locations-lite`).
3. Desactivar temporalmente MultiLoca o cargar la página antes de `init` y verificar que la sección puede forzarse con **Activar compatibilidad MultiLoca manualmente**.
4. Completar **Ubicaciones MultiLoca manuales** con identificadores, *slug* o nombres exactos y guardar; la tabla debe mostrar las ubicaciones normalizadas tras recargar.

## Flujo de pedido y metadatos
- Realizar una compra asignando una ubicación desde el widget/campo de MultiLoca y finalizar el pedido.
- En los artículos del pedido, validar que el metadato `_woo_contifico_multiloca_location` se rellena y replica valores provenientes de claves `_multiloca_location*` o `wcmlim_location*` cuando existen.
- Emitir un reembolso parcial y confirmar que los artículos reembolsados heredan el metadato de ubicación original.

## Sincronización de inventario con `wcmlim_stock_at_{ID}`
- Forzar una sincronización manual desde **WooCommerce → Contífico → Sincronización** o dejar que la tarea programada corra.
- En productos simples, comprobar que el metadato `wcmlim_stock_at_{ID}` se actualiza con el stock de la bodega asignada y que MultiLoca refleja la cantidad.
- En productos variables, modificar el stock de una variación y confirmar que el padre recibe la suma agregada de `wcmlim_stock_at_{ID}` para todas sus variaciones.
- Repetir las pruebas con Lite y con la variante premium para asegurar que ambos esquemas de datos responden igual.

## Notas adicionales
- Si MultiLoca expone funciones globales (`multiloca_lite_update_stock`, `multiloca_lite_get_locations`, etc.), verificar que siguen disponibles tras actualizaciones mayores de WooCommerce.
- Documentar cualquier cambio en taxonomías o metadatos detectado durante las pruebas para ajustar el mapeo de ubicaciones en Woo Contífico.
