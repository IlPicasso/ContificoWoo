=== Woocommerce - Facturación Electrónica - Contífico ===
Tags: Facturación electrónica, Contífico
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 5.8
WC tested up to: 10.3
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
   - Cuando Contífico usa el formato `CODIGOMADRE/TALLA` u otras variantes con `/` para identificar tallas o atributos, el
     sincronizador buscará automáticamente la variación correspondiente en WooCommerce (ya sea que el SKU local use guiones,
     guiones bajos o no incluya separadores) y actualizará sus bodegas, incluso si solo las variaciones tienen activada la
     gestión de inventario.
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

= 4.1.35 =
* Amplía el diagnóstico de ítems para incluir cualquier tipo de producto de WooCommerce (salvo variaciones), de modo que se reporten los productos sin coincidencias en Contífico aunque usen tipos personalizados.

= 4.1.34 =
* Registra las sincronizaciones programadas en el historial con sus tiempos y resultados para que queden visibles en la pestaña de sincronizaciones.

= 4.1.33 =
* Muestra alertas urgentes en el escritorio de WordPress cuando fallan procesos de inventario en Contífico.
* Añade una pestaña de "Bodega web" para consultar productos con stock pendiente en la bodega de facturación.

= 4.1.32 =
* Verifica el stock disponible en la bodega de facturación antes de restituir inventario y omite o limita las transferencias cuando faltan existencias o identificadores de producto.

= 4.1.31 =
* Corrige un fatal error de redeclaración al resolver los detalles de factura para los reportes e informes PDF.

= 4.1.30 =
* Hace clicable el número de factura en el PDF para abrir el RIDE cuando exista enlace disponible.
* Añade enlaces rápidos al RIDE y al reporte PDF desde la lista de pedidos y el detalle de la orden en WooCommerce.

= 4.1.29 =
* Almacena la ID y el enlace RIDE de la factura generada para mostrarlos en el PDF cuando el pedido esté completado.

= 4.1.28 =
* Muestra en el PDF que el pedido está completado y la factura fue generada cuando exista una factura asociada al pedido.

= 4.1.27 =
* Añade en los movimientos del PDF el motivo del ajuste de inventario usando la causa reportada (venta, reintegro, sincronización manual, etc.).

= 4.1.26 =
* Usa el campo `nombre` entregado por la API de Contífico como etiqueta principal de las bodegas para que los PDFs muestren los nombres oficiales en lugar de los códigos.

= 4.1.25 =
* Recupera los nombres oficiales de las bodegas desde la API de Contífico cuando los mapas de MultiLoca no proveen etiquetas, evitando que los PDFs repitan nombres amigables incorrectos.

= 4.1.24 =
* Conserva los nombres de cada bodega al formatear etiquetas para movimientos y transferencias, usando los alias mapeados solo cuando faltan los nombres propios.

= 4.1.23 =
* Ajusta la construcción de etiquetas de bodega para usar solo nombres amigables mapeados cuando existan y evitar que se reemplacen por ubicaciones repetidas en los PDFs.

= 4.1.22 =
* Conserva y reutiliza los códigos o IDs de bodega cuando faltan en el movimiento para que las etiquetas amigables sigan distinguiendo entre origen y destino en el PDF.

= 4.1.21 =
* Ajusta las etiquetas de bodega para conservar los nombres específicos cuando hay coincidencias de ubicación y añade en las filas de productos la bodega de origen y destino usada en los movimientos.

= 4.1.20 =
* Evita que las transferencias y movimientos del PDF muestren la misma bodega en origen y destino al reetiquetar con códigos o IDs cuando comparten nombre amigable.

= 4.1.19 =
* Ajusta la construcción de etiquetas de bodega en los PDFs para evitar que origen y destino se dupliquen cuando falta el identificador de ubicación, manteniendo los nombres amigables y códigos cuando estén disponibles.

= 4.1.18 =
* Corrige los textos de movimientos y transferencias en PDF para que ya no repitan la misma bodega de origen y destino cuando solo se conoce la ubicación principal.

= 4.1.17 =
* Se permite asignar un nombre amigable a la bodega de facturación (por ejemplo, "Bodega WEB") para que aparezca en PDFs y mensajes incluso cuando no está asociada a una ubicación de MultiLoca.

= 4.1.16 =
* Los PDFs de movimientos y transferencias ahora recuperan el nombre amigable de MultiLoca aun cuando solo está disponible el identificador de ubicación, evitando que se muestre solo el código de bodega.

= 4.1.15 =
* Se usa el nombre amigable configurado en MultiLoca (junto al código de bodega) en todas las secciones del PDF, incluidos los listados de movimientos y transferencias.

= 4.1.14 =
* Los PDFs de movimientos y transferencias ahora muestran el nombre amigable configurado en MultiLoca para las bodegas asociadas y conservan el código de bodega entre paréntesis para mayor claridad.

= 4.1.13 =
* Los PDFs de movimientos y transferencias priorizan el nombre mapeado de MultiLoca para las bodegas y muestran entre paréntesis el código de bodega en lugar del ID interno.

= 4.1.12 =
* Se fija la columna de "Detalle del pedido" en el margen derecho después del título para que no retome el margen izquierdo tras imprimir el encabezado.

= 4.1.11 =
* Se alinea el bloque del cliente con el margen izquierdo fijo en el PDF para evitar que se solape con el bloque de "Detalle del pedido".

= 4.1.10 =
* Se dividen automáticamente las líneas largas del bloque del cliente en el PDF para evitar que correos o teléfonos sin espacios se sobrepongan al bloque de "Detalle del pedido".

= 4.1.9 =
* Se equilibran los anchos de las dos columnas del PDF para que el bloque de "Detalle del pedido" conserve su espacio y no se monte sobre la dirección.

= 4.1.8 =
* Se desplaza la columna de "Detalle del pedido" hacia la derecha y se estrecha levemente el ancho de ambos bloques para evitar que el número de pedido invada el espacio de la dirección.

= 4.1.7 =
* Se aumenta el interlineado y se agrega separación extra en el bloque de datos del cliente en el PDF para que no se superpongan con otros textos.

= 4.1.6 =
* Se agrega espacio extra entre las líneas del bloque "Detalle del pedido" del PDF para evitar que se superpongan.
* Los movimientos y transferencias muestran los nombres mapeados en MultiLoca para las bodegas en lugar de solo el código.

= 4.1.5 =
* Se corrige la alineación del encabezado en el PDF para evitar que las líneas de dirección se sobrepongan con el área del logo.

= 4.1.4 =
* El refresco de stock en tiempo real se activa en páginas de producto también cuando las variaciones administran su propio inventario.
* Se excluye el script de actualización de stock de la optimización de carga diferida de WP Rocket para mantener las peticiones AJAX en caché activa.
* Los ajustes de stock disparados desde la página de producto se registran en el historial de movimientos sin duplicar egresos.

= 4.1.3 =
* Se actualiza la información del plugin para reflejar a IlPicasso como autor y se apunta el auto-actualizador al nuevo repositorio público.
* Cobertura validada con WordPress 6.8.3 y WooCommerce 10.3.5.

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