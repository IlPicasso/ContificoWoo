# Guía de verificación manual

Esta guía resume los pasos mínimos para validar la integración con Contífico y WooCommerce.

## Preparación del entorno
- WordPress 6.5+ con WooCommerce actualizado a la versión declarada en el encabezado del plugin.
- Credenciales de Contífico configuradas en **WooCommerce → Contífico → Integración**.
- Al menos un producto simple y uno variable con gestión de inventario habilitada.

## Configuración de bodegas
1. En Contífico, abrir **Inventario → Bodegas** y anotar los códigos de las bodegas que se usarán.
2. En **WooCommerce → Contífico → Integración**, registrar los códigos en **Bodega principal** y, si aplica, en **Bodega secundaria**, **Bodega terciaria** y **Bodega de facturación**.
3. Guardar los cambios y confirmar que no se muestran errores de configuración.

## Sincronización de inventario
- Ejecutar una sincronización manual desde **WooCommerce → Contífico → Sincronización**.
- Verificar que el stock y el precio del producto se actualizan en WooCommerce con los valores esperados desde Contífico.
- Revisar el resumen de sincronización para confirmar que se muestran las existencias por bodega.

## Flujo de pedido y facturación
- Realizar una compra de prueba con un producto en stock.
- Emitir el documento electrónico desde WooCommerce y validar que el inventario se descuenta de la bodega configurada.
- Si existe bodega de facturación, confirmar que el traslado y la restitución se registran correctamente en los movimientos.
