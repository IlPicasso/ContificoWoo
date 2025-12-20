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

= 4.2.2 =
* Se elimina la compatibilidad con plugins de ubicaciones externas.
* Se actualiza la documentación sobre la configuración de bodegas de Contífico.

* Historial anterior disponible en el repositorio.
