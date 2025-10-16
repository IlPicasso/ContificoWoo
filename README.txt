=== Woocommerce - Facturación Electrónica - Contífico ===
Tags: Facturación electrónica, Contífico
Requires at least: 5.0
Tested up to: 5.9
Requires PHP: 7.2
WC requires at least: 5.0
WC tested up to: 6.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integración simple de facturación electrónica para woocommerce a través del servicio de Contífico. Servicio solo válido en Ecuador

== Description ==

Este plugin está desarrollado para funcionar con la facturación electrónica desarrollada por Contífico.

Este plugin premium se ofrece con actualizaciones y soporte por un año. Para obtener actualizaciones futuras es necesario contratar
una extensión de la licencia.

=== Sincronización de inventario por ubicaciones (MultiLoca) ===

Desde la versión 3.5.0 se incluye compatibilidad con el plugin **MultiLoca Lite** para sincronizar el stock de Contífico por cada
ubicación configurada en WooCommerce. Esto permite reflejar el inventario de bodegas independientes en las ubicaciones de MultiLoca
y mantener actualizado el stock global de la tienda con la suma de todas ellas.

Para activar esta funcionalidad es necesario:

1. Tener instalado y activo el plugin [MultiLoca Lite](https://wordpress.org/plugins/multiloca-lite/) junto con WooCommerce.
2. Configurar las ubicaciones que administrará MultiLoca (por ejemplo, cada sucursal o punto de entrega).
3. Ingresar al menú **WooCommerce → Contífico → Integración**. Si MultiLoca Lite (o la versión premium de MultiLoca) está activo
   y tiene ubicaciones configuradas, al final del formulario aparecerá la sección **Compatibilidad MultiLoca** para asignar la
   bodega de Contífico correspondiente a cada ubicación listada.
   - Si la sección no se muestra, activa la casilla **Activar compatibilidad MultiLoca manualmente** (disponible desde la versión
     4.1.2) para forzar la carga de la sección, incluso cuando el plugin no sea detectado automáticamente.
   - Cuando uses la activación manual, completa el campo **Ubicaciones MultiLoca manuales** con un identificador por línea o en
     el formato `ID|Nombre` (por ejemplo `sucursal-centro|Sucursal Centro`). También puedes ingresar el *slug* o el nombre exacto
     de la ubicación que aparece en MultiLoca; Woo Contífico resolverá el identificador numérico correcto durante la
     sincronización para actualizar el inventario.
   - Si prefieres la detección automática, verifica que el plugin MultiLoca esté activo, que tenga al menos una ubicación creada
     y vuelve a cargar la página de integración. Cuando MultiLoca y Woo Contífico se inicializan al mismo tiempo (por ejemplo,
     al cargar el panel de administración tras activar ambos plugins), la compatibilidad espera a que WordPress termine de
     ejecutar todos los callbacks de `init` antes de evaluar la disponibilidad para asegurar que las taxonomías como
     `locations-lite` ya estén registradas.
   - La integración detecta MultiLoca incluso cuando el plugin no expone una instancia global y espera a que WordPress cargue todos los plugins activos antes de evaluarla. Además, si la página de ajustes se renderiza antes de que MultiLoca registre sus taxonomías en `init`, la compatibilidad consulta directamente la base de datos para listar las ubicaciones existentes, evitando que la sección aparezca vacía por cargar la página demasiado pronto. Si continúas sin ver la sección,
     confirma que la taxonomía o el tipo de contenido `multiloca_location` existan en tu sitio o que la opción
     `multiloca_locations` contenga ubicaciones guardadas. En ausencia de estos indicadores, contacta al soporte de MultiLoca
     para asegurarte de que las funciones `multiloca_lite_get_locations`/`multiloca_get_locations` estén disponibles.
   - Las variantes recientes de MultiLoca Lite distribuidas por Techspawn pueden registrar la taxonomía `locations-lite` y
     gestionar el inventario mediante metadatos `wcmlim_stock_at_{ID}`. Esta integración reconoce automáticamente ese esquema y
     actualizará el stock escribiendo en dichos metadatos cuando no haya funciones públicas disponibles.
   - Si sincronizas productos variables, Woo Contífico sumará automáticamente el stock de cada variación por ubicación y lo
     guardará en el producto padre para que los listados y widgets de MultiLoca muestren la disponibilidad correcta sin pasos
     manuales.
4. Asegurarse de que WooCommerce tenga habilitado el manejo de inventario global (*WooCommerce → Ajustes → Productos → Inventario → Habilitar la gestión de inventario*).

Una vez guardados los cambios, la sincronización de inventario (manual o automática) consultará el stock de cada bodega mapeada en
Contífico, actualizará la cantidad de su respectiva ubicación en MultiLoca y, finalmente, ajustará el stock global del producto en
WooCommerce con la suma de todas las bodegas configuradas. Si MultiLoca no está activo o no se han configurado ubicaciones, el
comportamiento vuelve al flujo tradicional de una sola bodega.

== Installation ==

Para instalar este plugin es necesario seguir estos pasos:

1. Ir a la sección de 'Plugins' de Wordpress y elegir "Añadir nuevo"
2. Seleccionar 'Subir plugin' y elegir del disco duro el archivo .zip
3. Activar el plugin de la lista de 'Plugins' de WordPress
4. Configurar los datos del establecimiento en 'Facturación electrónica' en el menú de WooCommerce.

== Frequently Asked Questions ==

= ¿Qué necesito para este usar este plugin? =

Para usar este plugin se requiere PHP 5.5 o superior, Wordpress 5.2 o superior, WooCommerce 3.6 o superior y una cuenta activa en Contífico.

= ¿Puedo usar este plugin sin una cuenta de Contífico? =

No, para poder usar este plugin es necesario que tengas una cuenta de Contífico.

== Changelog ==

= 4.1.2 =
* Se añade la activación manual de la compatibilidad con MultiLoca y el soporte para definir ubicaciones manuales.
* Se documenta el flujo de configuración manual para cuando la detección automática no está disponible.

= 4.1.1 =
* Se corrige error fatal cuando no se consigue el ID de la bodega

= 4.1.0 =
* Se agrega soporte para PHP 8

= 4.0.0 =
* Se remueve la licencia y se termina con el soporte del plugin

= 3.4.2 =
* Se agrega soporte para descuentos del 100%
* Se mejora el proceso de control de licencia para solucionar el error de licencias que se desactivan

= 3.4.1 =
* Se reubica la posición del campo "Tipo de personería" en el checkout para evitar confusiones
* Se actualiza el SDK de licencia

= 3.4.0 =
* Se agrega la fecha como número de lote al realizar pagos con emisión de factura

= 3.3.2 =
* ERROR CRITICO: Actualiza la URL de conexión al API de Contífico según notificación enviada a todos los clientes
* Se corrige una notificación de error cuando no estaba configurada la variable SERVER_SCHEME

= 3.3.1 =
* Se corrige un error con la actualización automática del plugin
* Se corrige un control para evitar errores cuando plugins de terceros modifican valores de los campos de identificación

= 3.3.0 =
* Se agrega la opción de sincronizar solo de modo manual
* Se corrige el cálculo de descuentos

= 3.2.9 =
* Permitir que se emitan cotizaciones, pre-facturas o facturas
* Modificada la forma de calcular el subtotal para evitar errores en productos de centavos
* Se revisa que el producto tenga ID de Contífico antes de enviar el documento (y obtenerlo si no lo tiene)

= 3.2.8 =
* Mejorado el manejo dela tarea de actualización de inventario

= 3.2.7 =
* Modificado el control de impuestos por línea de pedido para evitar IVA duplicado

= 3.2.6 =
* Corregido un error al enviar el valor unitario de un producto

= 3.2.5 =
* Corregido un error al calcular el IVA de envio con precio con centavos

= 3.2.4 =
* Corregido un error al calcular el IVA de productos con precios con centavos

= 3.2.3 =
* Corregido un error al calcular el IVA de productos sin impuesto

= 3.2.2 =
* Corrección de sincronización para productos que no manejan inventario

= 3.2.1 =
* Arreglos menores al movimiento de inventario

= 3.2.0 =
* Se agrega la opción de sincronizar el precio desde cualquier PVP de Contífico
* Se corrige un error al validar la cédula de extranjeros
* Se corrige un error de redondeo para facturas productos con costos en centavos
* Se corrige un error que no retornaba los productos de la bodega de facturación a la bodega principal

= 3.1.0 =
* Se agrega registro de llamadas al API de Contífico

= 3.0.1 =
* Se arregla un error al final del pago del cliente que causaba un error fatal

= 3.0.0 =
* Se añade una bodega adicional para mover el inventario y evitar que un producto se venda dos veces
* Se crea una pestaña para la sincronización manual, removiéndola de su ubicación en la pestaña de integración
* Se corrige el flujo y mejora la documentación

= 2.2.0 =
* Se añade automáticamente "pago con tarjeta de crédito" como método de pago al facturar en lugar de "Efectivo" como se hacía antes
* Se remueve la opción de reducir el inventario antes de generar la factura electrónica ya que se están generando errores de inventarios reducidos dos veces
* Mejoras de código y velocidad

= 2.1.1 =
* Se remueve la reducción de stock al realizar la compra ya que Contífico realiza la misma acción automáticamente al emitir la factura y eso genera errores

= 2.1.0 =
* Se corrige el error que desactiva la licencia inesperadamente
* Se agrega una notificación indicando que el plugin está configurado en modo de pruebas
* Agrega una notificación indicando que faltan configuraciones en el plugin

= 2.0.3 =
* Se agregan campos de configuración por entorno para hacer más fácil las pruebas y el paso a producción
* Se corrige el error de reducción duplicada de inventario
* Se corrige el error de sincronización de muchos productos

= 2.0.2 =
* Se arregla error de product_id al emitir la factura
* Se arregla un error de redondeó del IVA

= 2.0.1 =
* Se arregla error de tamaño del lote en sincronización manual
* Se arregla error de stock no actualizado para productos variables
* Se arregla error con productos variables a la hora de emitir facturas

= 2.0.0 =
* Se agrega sincronización por lotes para bodegas con muchos productos
* Se mejora la ejecución de sincronización para consumir menor recursos
* Se agrega la sincronización de precio de venta desde Contífico
* Se mejora la pantalla de integración para agregar más información

= 1.6.0 =
* Se corrigieron algunos errores
* Se agregó información de error de licencia
* Secuencia de factura movida de Integración a POS

= 1.5.0 =
* Integración con el plugin de actualizaciones
* Control de activación de licencia

= 1.4.0 =
* Se agrega integración con facturación electrónica
* Se agrega el tipo de contribuyente
* Se solucionan problemas de sincronización de inventario
* Se agregan notificaciones al guardar configuraciones
* Se reubica la imagen de carga de productos desde Contífico
* Solucionado error al encriptar firma
* Se agregan campos requeridos al emisor
* Arreglado problema con el shipping
* Agregado soporte para "tipo de contribuyente"
* Se corrige un error en la definición del campo "tipo de documento" que generaba error en backend
* Notificación de configuración pendiente
* Se agrega opción para eliminar configuraciones al desactivar el plugin
* Se añade un spinner propio de WP

= 1.3.0 =
* Se realiza la integración con Contífico
* Se realiza la sincronización de inventario
* Ser realiza el bloqueo y restitución de inventario al comprar y cancelar la compra

= 1.2.0 =
* Se agrega la página de configuración

= 1.1.0 =
* Added auto update support
* Added license support

= 1.0.0 =
* Initial configuration

== Upgrade Notice ==

= 4.0.1 =
* Se agrega soporte para PHP 8

= 4.0.0 =
* Se remueve la licencia y se termina con el soporte del plugin

= 3.4.2 =
* Se agrega soporte para descuentos del 100%

= 3.4.1 =
* Se reubica la posición del campo "Tipo de personería" en el checkout para evitar confusiones

= 3.4.0 =
* Se agrega la fecha como número de lote al realizar pagos con emisión de factura

= 3.3.2 =
* ERROR CRITICO: Actualiza la URL de conexión al API de Contífico según notificación enviada a todos los clientes

= 3.3.1 =
* Se corrige un control para evitar errores cuando plugins de terceros modifican valores de los campos de identificación

= 3.3.0 =
* Se agrega la opción de sincronizar solo de modo manual

= 3.2.9 =
* Permitir que se emitan cotizaciones, pre-facturas o facturas

= 3.2.8 =
* Mejorado el manejo dela tarea de actualización de inventario

= 3.2.7 =
* Modificado el control de impuestos por línea de pedido para evitar IVA duplicado

= 3.2.6 =
* Corregido un error al enviar el valor unitario de un producto

= 3.2.5 =
* Corregido un error al calcular el IVA de envio con precio con centavos

= 3.2.4 =
* Corregido un error al calcular el IVA de productos con precios con centavos

= 3.2.3 =
* Corregido un error al calcular el IVA de productos sin impuesto

= 3.2.2 =
* Corrección de sincronización para productos que no manejan inventario

= 3.2.1 =
* Arreglos menores al movimiento de inventario

= 3.2.0 =
* Se agrega la opción de sincronizar el precio desde cualquier PVP de Contífico

= 3.1.0 =
* Se agrega registro de llamadas al API de Contífico

= 3.0.1 =
* Se arregla un error al final del pago del cliente que causaba un error fatal

= 3.0.0 =
* Se añade una bodega adicional para mover el inventario y evitar que un producto se venda dos veces

= 2.2.0 =
* Se añade automáticamente "pago con tarjeta de crédito" como método de pago al facturar en lugar de "Efectivo" como se hacía antes

= 2.1.1 =
* Se remueve la reducción de stock al realizar la compra ya que Contífico realiza la misma acción automáticamente al emitir la factura y eso genera errores

= 2.1.0 =
* Se corrige el error que desactiva la licencia inesperadamente

= 2.0.3 =
* Se agregan campos de configuración por entorno para hacer más fácil las pruebas y el paso a producción

= 2.0.2 =
* Se arregla error de product_id al emitir la factura

= 2.0.1 =
* Se arregla error con productos variables a la hora de emitir facturas

= 2.0.0 =
* Se agrega sincronización por lotes para bodegas con muchos productos

= 1.6 =
Completado el control de licencias

= 1.5 =
Se implementa la actualización automática

= 1.4 =
Se agrega integración con la factura electrónica

= 1.3 =
Se realiza la actualización de inventario

= 1.2 =
Página de configuración agregada

= 1.1 =
Se agrega actualización automática del plugin