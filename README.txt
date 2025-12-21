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

=== Configuración de bodegas de Contífico ===

Para agregar y gestionar bodegas de Contífico en ContificoWoo:

1. En Contífico, abre **Inventario → Bodegas** y toma nota de los códigos de cada bodega.
2. En **WooCommerce → Contífico → Integración**, completa los campos de **Manejo de bodegas**:
   - **Bodega principal**: bodega desde la que se sincroniza el inventario.
   - **Bodega secundaria/terciaria**: bodegas de respaldo si necesitas cubrir pedidos con stock alternativo.
   - **Bodega de facturación**: bodega donde se descuenta el inventario al emitir la factura o pre factura (opcional).
   - **Nombre amigable de la bodega de facturación**: etiqueta que aparecerá en PDFs y mensajes (opcional).
   - **Bodegas visibles en ítems**: códigos que quieres mostrar en los reportes de stock por bodega.
3. Guarda los cambios.
4. Asegúrate de que WooCommerce tenga habilitada la gestión de inventario (*WooCommerce → Ajustes → Productos → Inventario → Habilitar la gestión de inventario*).

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

= 4.2.13 =
* El meta box de stock por bodega ahora considera variaciones con ID de Contífico cuando el producto variable no lo tiene.

= 4.2.12 =
* Se añade un meta box en productos para listar stock por bodega según el ID de Contífico y las bodegas visibles configuradas.

= 4.2.11 =
* Se abortan solicitudes de stock anteriores al cambiar variaciones rápidamente.

= 4.2.10 =
* Se elimina la validación duplicada de "Gestionar inventario" al sincronizar variaciones.

= 4.2.9 =
* Se vuelve a incluir el código de bodega en el payload para filtrar correctamente las variaciones sin mostrar el ID en el frontend.

= 4.2.8 =
* Se muestra solo el nombre amigable en el listado de stock por bodega y se evita sobrescribir con respuestas AJAX antiguas.

= 4.2.7 =
* Se evita el listado duplicado de bodegas al construir el detalle por bodega en el frontend.

= 4.2.6 =
* Se añade un selector de bodegas en Configuración para elegir bodegas visibles y definir nombres amigables.

= 4.2.5 =
* El stock por bodega en el frontend solo se muestra cuando hay bodegas visibles configuradas.

= 4.2.4 =
* Se muestra el detalle de stock por bodega en la página de producto.
* Se añade un selector configurable para el bloque de bodegas y se filtra el render con las bodegas visibles.

= 4.2.3 =
* Se agrega la configuración de bodegas visibles en los ítems de stock por bodega.
* Se filtra el stock por bodega para mostrar solo las bodegas registradas.

* Historial anterior disponible en el repositorio.
