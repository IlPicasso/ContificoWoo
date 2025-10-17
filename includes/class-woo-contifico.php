<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://otakupahp.com/quien-es-pablo-hernandez-otakupahp
 * @since      1.0.0
 *
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Contifico
 * @subpackage Woo_Contifico/includes
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 */
class Woo_Contifico
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Contifico_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Plugin settings
     *
     * @since    1.2.0
     * @access   public
     * @var      array $settings Plugin setting from database
     */
    public $settings;

    /**
     * Plugin settings fields
     *
     * @since    1.2.0
     * @access   public
     * @var      array $settings_fields Plugin settings fields
     */
    public $settings_fields;

    /**
     * MultiLoca compatibility handler.
     *
     * @since    3.5.0
     * @access   public
     * @var      Woo_Contifico_MultiLocation_Compatibility|null
     */
    public $multilocation;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @param bool $load Used to limit dependencies loading
     * @since    1.0.0
     */
    public function __construct($load = TRUE)
    {
        if (defined('WOO_CONTIFICO_VERSION')) {
            $this->version = WOO_CONTIFICO_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'woo-contifico';

        if ( ! class_exists( 'Woo_Contifico_MultiLocation_Compatibility' ) ) {
            require_once WOO_CONTIFICO_PATH . 'includes/compat/class-woo-contifico-multilocation.php';
        }

        $this->multilocation = new Woo_Contifico_MultiLocation_Compatibility();

        if ( $load ) {
            $this->load_dependencies();
        }

        $this->set_locale();
        $this->init_settings();

        if ($load) {
            $this->define_admin_hooks();
            $this->define_public_hooks();
        }

    }

    /*
     * Initialize settings variables
     */
    private function init_settings()
    {
        $setting_status = [];
        $setting_status['woocommerce'] = get_option('woo_contifico_woocommerce_settings', [] );
        $setting_status['contifico'] = get_option('woo_contifico_integration_settings', [] );
        $setting_status['emisor'] = get_option('woo_contifico_sender_settings', [] );
        $setting_status['establecimiento'] = get_option('woo_contifico_pos_settings', [] );

        # Settings configuration status
        $result = [];
        foreach ($setting_status as $key => $value) {
        	$arr = array_values($value);
            $result[$key] = empty($arr[0]);
        }

        $setting_status[] = [ 'settings_status' => $result ];
        $this->settings = call_user_func_array('array_merge', array_values($setting_status));

        if ( ! isset( $this->settings['multiloca_locations'] ) || ! is_array( $this->settings['multiloca_locations'] ) ) {
            $this->settings['multiloca_locations'] = [];
        }

        if ( ! isset( $this->settings['multiloca_manual_enable'] ) ) {
            $this->settings['multiloca_manual_enable'] = false;
        } else {
            $this->settings['multiloca_manual_enable'] = (bool) $this->settings['multiloca_manual_enable'];
        }

        if ( ! isset( $this->settings['multiloca_manual_locations'] ) ) {
            $this->settings['multiloca_manual_locations'] = '';
        } else {
            $this->settings['multiloca_manual_locations'] = (string) $this->settings['multiloca_manual_locations'];
        }

        $manual_locations = [];

        if ( ! empty( $this->settings['multiloca_manual_locations'] ) ) {
            $manual_locations = $this->parse_manual_multiloca_locations( $this->settings['multiloca_manual_locations'] );
        }

        if ( $this->multilocation instanceof Woo_Contifico_MultiLocation_Compatibility ) {
            if ( method_exists( $this->multilocation, 'set_manual_activation' ) ) {
                $this->multilocation->set_manual_activation( (bool) $this->settings['multiloca_manual_enable'] );
            }

            if ( method_exists( $this->multilocation, 'set_manual_locations' ) ) {
                $this->multilocation->set_manual_locations( $manual_locations );
            }
        }

        # Back compatibility, use old api configuration
            if( isset($this->settings['ambiente']) ) {
                    $env                                             = ( (int) $this->settings['ambiente'] === WOO_CONTIFICO_TEST ) ? 'test' : 'prod';
                    $this->settings["{$env}_api_key"]                = $this->settings["{$env}_api_key"] ?? $this->settings['api_key'];
		    $this->settings["{$env}_api_token"]              = $this->settings["{$env}_api_token"] ?? $this->settings['api_token'];
		    $this->settings["{$env}_establecimiento_punto"]  = $this->settings["{$env}_establecimiento_punto"] ?? $this->settings['establecimiento_punto'];
		    $this->settings["{$env}_establecimiento_codigo"] = $this->settings["{$env}_establecimiento_codigo"] ?? $this->settings['establecimiento_codigo'];
		    $this->settings["{$env}_secuencial_factura"]     = $this->settings["{$env}_secuencial_factura"] ?? $this->settings['secuencial_factura'];
	    }

	    # Back compatibility, use old sync price
	    if( isset($this->settings['sync_price']) && (int) $this->settings['sync_price'] === 1 ) {
		    $this->settings['sync_price'] = 'pvp1';
	    }
	    elseif( !isset($this->settings['sync_price']) || empty($this->settings['sync_price'])) {
		    $this->settings['sync_price'] = 'no';
	    }

	    # Back compatibility, use factura as default document
	    if( !isset($this->settings['tipo_documento']) || empty($this->settings['tipo_documento'])) {
		    $this->settings['tipo_documento'] = 'FAC';
	    }

        $this->settings_fields = [
                'woo_contifico_woocommerce' => [
                'name' => __('Configuración WooCommerce', $this->plugin_name),
                'description' => __('Opciones utilizadas en WooCommerce para emitir el documento electrónico automáticamente', $this->plugin_name),
                'validation_function' => 'save_settings',
                'fields' => [
                    [
                        'id' => 'etapa_envio',
                        'label' => __('Enviar del documento en el estado', $this->plugin_name),
                        'description' => __(' (Estado de la orden en la que se solicitará a Contífico que emita el documento)', $this->plugin_name),
                        'required' => true,
                        'type' => 'select',
                        'options' => [
                        	'' => __('Elige una etapa de envío', $this->plugin_name),
                            'processing' => __('Procesando pedido', $this->plugin_name),
                            'complete' => __('Pedido completado', $this->plugin_name),
                        ]
                    ],
	                [
		                'id' => 'tipo_documento',
		                'label' => __('Tipo de documento a emitir', $this->plugin_name),
		                'description' => __('El tipo documento que debe emitirse desde Contífico<br>', $this->plugin_name),
		                'required' => true,
		                'type' => 'select',
		                'options' => [
			                'FAC' => __('Factura', $this->plugin_name),
			                'PRE' => __('Pre Factura', $this->plugin_name),
			                'COT' => __('Cotización', $this->plugin_name),
		                ]
	                ],
	                [
		                'id' => 'borrar_configuracion',
		                'label' => __('Borrar datos al desactivar', $this->plugin_name),
		                'description' => __('(Eliminar datos de la configuración al desactivar el plugin)', $this->plugin_name),
		                'required' => false,
		                'type' => 'check',
	                ],
	                [
		                'id' => 'activar_registro',
		                'label' => __('Activar registro de llamadas API', $this->plugin_name),
		                'description' => 'Activar registro de todas las llamadas al API de Contifico. (Al desactivar esta opción se eliminará el archivo)',
		                'required' => false,
		                'type' => 'check',
	                ],
                ]
	        ],
            'woo_contifico_integration' => [
                'name' => __('Configuración Integración Contífico', $this->plugin_name),
                'description' => __(
                	'<p><b>IMPORTANTE:</b> A continuación una explicación de los distintos campos que se deben ingresar.</p>
						<ul>
							<li>La clave y el token API son entregados por Contífico al momento de solicitar el acceso al API. Hay un par de Key/Token por cada ambiente</li>
							<li>El código de envío en el código de producto en Contífico para poder emitir correctamente el documento. Sin este producto no será posible cobrar envío en los productos.</li>
							<li>La sincronización buscará el código del producto de Contífico y lo comparará con el SKU del producto en WooCommerce, traerá el inventario de la bodega definida y el PVP1 del producto para actualizar el precio regular del producto.</li>
							<li>Número de productos a procesar por bloque. Se utiliza un número que sirve en la mayoría de servidores, pero puedes aumentar o reducir este número dependiendo de la capacidad de tu servidor.</li>
							<li>Cuando Contífico entrega las credenciales automáticamente asocia una bodega con la facturación. Es decir, cuando se emita una nueva factura o pre factura, el inventario será reducido de esta bodega. Esto funciona para la mayoría de negocios, pero si quieres evitar inventarios negativos debes crear dos bodegas, una para inventario y una de facturación.</li>
						</ul>
					', $this->plugin_name),
                'validation_function' => 'validate_integration',
                'fields' => [
	                [
	                	'id' => 'title_1',
		                'type' => 'title',
		                'label' => __('<h3>Conexión</h3>', $this->plugin_name),
	                ],
	                [
		                'id' => 'ambiente',
		                'label' => __('Ambiente', $this->plugin_name),
		                'description' => '<br>&nbsp;',
		                'required' => true,
		                'type' => 'radio',
		                'options' => [
			                WOO_CONTIFICO_TEST => __('Pruebas', $this->plugin_name),
			                WOO_CONTIFICO_PRODUCTION => __('Producción', $this->plugin_name),
		                ]
	                ],
	                [
		                'id' => 'title_1_1',
		                'type' => 'title',
		                'label' => __('<h4>Datos ambiente de prueba</h4>', $this->plugin_name),
	                ],
                	[
		                'id' => 'test_api_key',
		                'label' => __('Clave del API (API Key)', $this->plugin_name),
		                'description' => '<br>&nbsp;',
		                'required' => false,
		                'type' => 'text',
		                'size' => 50,
	                ],
	                [
		                'id' => 'test_api_token',
		                'label' => __('Token del API (API Token)', $this->plugin_name),
		                'description' => '<br>&nbsp;',
		                'required' => false,
		                'type' => 'text',
		                'size' => 50,
	                ],
	                [
		                'id' => 'title_1_2',
		                'type' => 'title',
		                'label' => __('<h4>Datos ambiente de producción</h4>', $this->plugin_name),
	                ],
	                [
		                'id' => 'prod_api_key',
		                'label' => __('Clave del API (API Key)', $this->plugin_name),
		                'description' => '<br>&nbsp;',
		                'required' => false,
		                'type' => 'text',
		                'size' => 50
	                ],
	                [
		                'id' => 'prod_api_token',
		                'label' => __('Token del API (API Token)', $this->plugin_name),
		                'description' => '<br>&nbsp;',
		                'required' => false,
		                'type' => 'text',
		                'size' => 50
	                ],
	                [
		                'id' => 'title_2',
		                'type' => 'title',
		                'label' => __('<h3>Envío</h3>', $this->plugin_name),
	                ],
	                [
		                'id' => 'shipping_code',
		                'label' => __('Código del envío', $this->plugin_name),
		                'description' => __('<br>Código del producto "envío" de Contífico para agregar el costo al documento. (Dejar en blanco si no cobra envío)', $this->plugin_name),
		                'required' => false,
		                'type' => 'text',
		                'size' => 20
	                ],
	                [
		                'id' => 'title_3',
	                	'type' => 'title',
		                'label' => __('<h3>Sincronización</h3>', $this->plugin_name),
	                ],
	                [
		                'id' => 'sync_price',
		                'label' => __('Sincronizar precio', $this->plugin_name),
		                'description' => __(' Actualizar el precio regular del producto a partir del PVP de Contífico<br>', $this->plugin_name),
		                'required' => false,
		                'type' => 'select',
		                'options' => [
			                'no' => __('No sincronizar el precio', $this->plugin_name),
			                'pvp1' => __('Usar PVP1', $this->plugin_name),
			                'pvp2' => __('Usar PVP2', $this->plugin_name),
			                'pvp3' => __('Usar PVP3', $this->plugin_name),
		                ]
	                ],
	                [
		                'id' => 'actualizar_stock',
		                'label' => __('Frecuencia', $this->plugin_name),
		                'description' => __('Cada cuanto tiempo se sincronizará con Contífico<br>', $this->plugin_name),
		                'required' => true,
		                'type' => 'select',
		                'options' => [
		                	'manual' => __('Sincronización manual', $this->plugin_name),
			                'daily' => __('Diario', $this->plugin_name),
			                'twicedaily' => __('Dos veces al día', $this->plugin_name),
			                'hourly' => __('Cada hora', $this->plugin_name),
		                ]
	                ],
	                [
		                'id' => 'batch_size',
		                'label' => __('Productos por bloque', $this->plugin_name),
		                'description' => __('Máximo aceptado por Contífico: 1000 productos por bloque', $this->plugin_name),
		                'required' => true,
		                'type' => 'text',
		                'size' => 5
	                ],
	                [
		                'id' => 'title_4',
		                'type' => 'title',
		                'label' => __('<h3>Manejo de bodegas</h3>', $this->plugin_name),
	                ],
	                [
		                'id' => 'bodega',
		                'label' => __('Bodega principal', $this->plugin_name),
		                'description' => __('<br>Código de la bodega desde donde se sincroniza el inventario.<br> Si se usan dos bodegas, esta será la bodega de inventario.', $this->plugin_name),
		                'required' => true,
		                'type' => 'text',
		                'size' => 10
	                ],
                        [
                                'id' => 'bodega_facturacion',
                                'label' => __('Bodega de facturación', $this->plugin_name),
                                'description' => __('<br><b>Dejar en blanco si no se usarán dos bodegas (Bodega de inventario y bodega de facturación)</b><br>Código de la bodega asociada con el API de Contífico a donde se traslada el producto al realizar un pedido en WooCommerce y desde donde se reduce el inventario al emitir la factura o pre factura', $this->plugin_name),
                                'required' => false,
                                'type' => 'text',
                                'size' => 10
                        ],
                        [
                                'id' => 'multiloca_manual_enable',
                                'label' => __('Activar compatibilidad MultiLoca manualmente', $this->plugin_name),
                                'description' => __('Marca esta casilla para mostrar el mapeo de ubicaciones aunque el plugin no se detecte automáticamente.', $this->plugin_name),
                                'required' => false,
                                'type' => 'check',
                        ],
                        [
                                'id' => 'multiloca_manual_locations',
                                'label' => __('Ubicaciones MultiLoca manuales', $this->plugin_name),
                                'description' => __('Ingresa un ID por línea o en el formato <code>ID|Nombre</code>. Solo se utiliza cuando la compatibilidad manual está activa.', $this->plugin_name),
                                'required' => false,
                                'type' => 'textarea',
                                'rows' => 5,
                        ],

                ]
            ],
            'woo_contifico_sender' => [
                'name' => __('Datos del emisor', $this->plugin_name),
                'description' => __('Datos del emisor de los documentos electrónicos para el SRI', $this->plugin_name),
                'validation_function' => 'validate_sender',
                'fields' => [
                    [
                        'id' => 'emisor_ruc',
                        'label' => __('RUC', $this->plugin_name),
                        'description' => '',
                        'required' => true,
                        'type' => 'text',
                        'size' => 15
                    ],
                    [
                        'id' => 'emisor_razon_social',
                        'label' => __('Razón social', $this->plugin_name),
                        'description' => '',
                        'required' => true,
                        'type' => 'text',
                        'size' => 30
                    ],
                    [
                        'id' => 'emisor_telefonos',
                        'label' => __('Teléfono', $this->plugin_name),
                        'description' => '',
                        'required' => true,
                        'type' => 'text',
                        'size' => 30
                    ],
                    [
                        'id' => 'emisor_direccion',
                        'label' => __('Dirección', $this->plugin_name),
                        'description' => '',
                        'required' => true,
                        'type' => 'text',
                        'size' => 50
                    ],
                    [
                        'id' => 'emisor_email',
                        'label' => __('Correo Electrónico', $this->plugin_name),
                        'description' => '',
                        'required' => true,
                        'type' => 'text',
                        'size' => 30
                    ],
	                [
		                'id' => 'tipo_contribuyente',
		                'label' => __('Tipo de contribuyente', $this->plugin_name),
		                'description' => '',
		                'required' => true,
		                'type' => 'radio',
		                'options' => [
			                'N' => __('Persona natural', $this->plugin_name),
			                'J' => __('Persona Jurídica', $this->plugin_name),
		                ]
	                ],
	                [
		                'id' => 'emisor_extranjero',
		                'label' => __('Extranjero', $this->plugin_name),
		                'description' => '',
		                'required' => true,
		                'type' => 'radio',
		                'options' => [
			                1 => __('Si', $this->plugin_name),
			                0 => __('No', $this->plugin_name),
		                ]
	                ]
                ]
            ],
            'woo_contifico_pos' => [
                'name' => __('Datos del establecimiento', $this->plugin_name),
                'description' => __('Opciones del punto de venta asociado a esta tienda en línea', $this->plugin_name),
                'validation_function' => 'validate_pos',
                'fields' => [
	                [
		                'id' => 'establecimiento_direccion',
		                'label' => __('Dirección del establecimiento', $this->plugin_name),
		                'description' => '',
		                'required' => true,
		                'type' => 'text',
		                'size' => 50
	                ],
	                [
		                'id' => 'test_establecimiento_title',
		                'type' => 'title',
		                'label' => '<h4>Datos ambiente de prueba</h4>',
	                ],
	                [
                        'id' => 'test_establecimiento_punto',
                        'label' => __('Código del establecimiento', $this->plugin_name),
                        'description' => '',
                        'required' => false,
                        'type' => 'text',
                        'size' => 3
                    ],
                    [
                        'id' => 'test_establecimiento_codigo',
                        'label' => __('Código del punto de emisión', $this->plugin_name),
                        'description' => '',
                        'required' => false,
                        'type' => 'text',
                        'size' => 3
                    ],
	                [
		                'id' => 'test_secuencial_factura',
		                'label' => __('Número secuencial de la factura o pre factura', $this->plugin_name),
		                'description' => __(' (Usar el número de la siguiente factura a emitir, no colocar 0s a la izquierda)', $this->plugin_name),
		                'required' => false,
		                'type' => 'text',
		                'size' => 5
	                ],
	                [
		                'id' => 'prod_establecimiento_title',
		                'type' => 'title',
		                'label' => '<h4>Datos ambiente de producción</h4>',
	                ],
	                [
		                'id' => 'prod_establecimiento_punto',
		                'label' => __('Código del establecimiento', $this->plugin_name),
		                'description' => '',
		                'required' => false,
		                'type' => 'text',
		                'size' => 3
	                ],
		            [
			            'id' => 'prod_establecimiento_codigo',
			            'label' => __('Código del punto de emisión', $this->plugin_name),
			            'description' => '',
			            'required' => false,
			            'type' => 'text',
			            'size' => 3
		            ],
		            [
			            'id' => 'prod_secuencial_factura',
			            'label' => __('Número secuencial de la factura o pre factura', $this->plugin_name),
			            'description' => __(' (Usar el número de la siguiente factura a emitir, no colocar 0s a la izquierda)', $this->plugin_name),
			            'required' => false,
			            'type' => 'text',
			            'size' => 5
		            ],
                ]
            ],
        ];

        if (
            $this->multilocation instanceof Woo_Contifico_MultiLocation_Compatibility
            && $this->multilocation->is_active()
        ) {
            $locations = $this->multilocation->get_locations();

            if ( ! empty( $locations ) ) {
                $multiloca_fields   = [];
                $multiloca_fields[] = [
                    'id'          => 'multiloca_locations_title',
                    'type'        => 'title',
                    'label'       => __('<h3>Compatibilidad MultiLoca</h3>', $this->plugin_name),
                    'description' => __( 'Asigna la bodega de Contífico correspondiente a cada ubicación gestionada por MultiLoca.', $this->plugin_name ),
                ];

                foreach ( $locations as $location_id => $location ) {
                    list( $normalized_id, $location_name ) = $this->prepare_multiloca_location_data( $location_id, $location );

                    $multiloca_fields[] = [
                        'id'                    => sprintf( 'multiloca_location_%s', $normalized_id ),
                        'label'                 => sprintf(
                            /* translators: %s: MultiLoca location name */
                            __( 'Bodega para %s', $this->plugin_name ),
                            $location_name
                        ),
                        'description'           => '',
                        'required'              => false,
                        'type'                  => 'select',
                        'options'               => [],
                        'custom_name'           => sprintf(
                            'woo_contifico_integration_settings[multiloca_locations][%s]',
                            $normalized_id
                        ),
                        'value_key'             => [ 'multiloca_locations', $normalized_id ],
                        'multiloca_location_id' => $normalized_id,
                    ];
                }

                $this->settings_fields['woo_contifico_integration']['fields'] = array_merge(
                    $this->settings_fields['woo_contifico_integration']['fields'],
                    $multiloca_fields
                );
            } elseif ( $this->settings['multiloca_manual_enable'] ) {
                $this->settings_fields['woo_contifico_integration']['fields'][] = [
                    'id'          => 'multiloca_manual_notice',
                    'type'        => 'title',
                    'label'       => __('<p><em>Agrega las ubicaciones manuales para habilitar el mapeo con Contífico.</em></p>', $this->plugin_name),
                ];
            }
        }
    }

    /**
     * Normalize the MultiLoca location data to a simple array with ID and name.
     *
     * @param string|int $location_id Default location identifier.
     * @param mixed      $location    Location payload from MultiLoca.
     *
     * @return array
     */
    private function prepare_multiloca_location_data( $location_id, $location ) : array {
        $id   = $location_id;
        $name = '';

        if ( is_array( $location ) ) {
            $id   = $location['id'] ?? $location['location_id'] ?? $location_id;
            $name = $location['name'] ?? $location['title'] ?? $location['slug'] ?? '';
        } elseif ( is_object( $location ) ) {
            $id   = $location->id ?? $location->location_id ?? $location->ID ?? $location_id;
            $name = $location->name ?? $location->title ?? $location->post_title ?? '';
        } elseif ( is_string( $location ) ) {
            $name = $location;
        }

        $id   = wp_strip_all_tags( (string) $id );
        $name = wp_strip_all_tags( trim( (string) $name ) );

        if ( '' === $name ) {
            $name = sprintf( __( 'Ubicación #%s', $this->plugin_name ), $id );
        }

        return [ $id, $name ];
    }

    /**
     * Convert the manual locations input into a normalized array.
     *
     * @param string $raw_locations
     *
     * @return array
     */
    private function parse_manual_multiloca_locations( string $raw_locations ) : array {
        $lines     = preg_split( '/[\r\n]+/', $raw_locations );
        $locations = [];

        if ( ! is_array( $lines ) ) {
            return $locations;
        }

        foreach ( $lines as $line ) {
            $line = trim( (string) $line );

            if ( '' === $line ) {
                continue;
            }

            $id   = $line;
            $name = '';

            if ( false !== strpos( $line, '|' ) ) {
                list( $id, $name ) = array_map( 'trim', explode( '|', $line, 2 ) );
            } elseif ( false !== strpos( $line, ':' ) ) {
                list( $id, $name ) = array_map( 'trim', explode( ':', $line, 2 ) );
            }

            $id   = wp_strip_all_tags( (string) $id );
            $name = wp_strip_all_tags( (string) $name );

            if ( '' === $id ) {
                continue;
            }

            if ( '' === $name ) {
                $name = $id;
            }

            $locations[ $id ] = [
                'id'   => $id,
                'name' => $name,
            ];
        }

        return $locations;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Woo_Contifico_Loader. Orchestrates the hooks of the plugin.
     * - Woo_Contifico_i18n. Defines internationalization functionality.
     * - Woo_Contifico_Admin. Defines all hooks for the admin area.
     * - Woo_Contifico_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

	    /**
	     * The class responsible for orchestrating the actions and filters of the
	     * core plugin.
	     */
	    require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-loader.php';

	    /**
	     * The class responsible for defining internationalization functionality
	     * of the plugin.
	     */
	    require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-i18n.php';

	    /**
	     * The class responsible for defining all actions that occur in the admin area.
	     */
	    require_once WOO_CONTIFICO_PATH . 'admin/class-woo-contifico-admin.php';

	    /**
	     * The class responsible for defining all actions that occur in the public-facing
	     * side of the site.
	     */
	    require_once WOO_CONTIFICO_PATH . 'public/class-woo-contifico-public.php';

	    /**
	     * Contifico SDK
	     */
	    require_once WOO_CONTIFICO_PATH . 'libraries/Contifico.php';

	    /**
	     * Diagnostics helper.
	     */
	    require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-diagnostics.php';

	$this->loader = new Woo_Contifico_Loader();

}

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Woo_Contifico_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {

        if ( ! class_exists( 'Woo_Contifico_i18n' ) ) {
            require_once WOO_CONTIFICO_PATH . 'includes/class-woo-contifico-i18n.php';
        }

        $plugin_i18n = new Woo_Contifico_i18n();

        if ( did_action( 'init' ) || doing_action( 'init' ) ) {
            $plugin_i18n->load_plugin_textdomain();
            return;
        }

        if ( isset( $this->loader ) && is_object( $this->loader ) && method_exists( $this->loader, 'add_action' ) ) {
            $this->loader->add_action('init', $plugin_i18n, 'load_plugin_textdomain');
            return;
        }

        add_action( 'init', [ $plugin_i18n, 'load_plugin_textdomain' ] );

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Woo_Contifico_Admin($this->get_plugin_name(), $this->get_version());

        # Styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        # Plugin settings link in plugins list table
        $plugin_basename = $this->plugin_name . '/' . $this->get_plugin_name();
        $this->loader->add_filter("plugin_action_links_{$plugin_basename}.php", $plugin_admin, 'add_settings_link');

        # Register admin actions
        $this->loader->add_action('admin_init', $plugin_admin, 'admin_init');
        $this->loader->add_action('admin_menu', $plugin_admin, 'register_menu');
        $this->loader->add_action('admin_notices', $plugin_admin, 'admin_init_notice');

        # Stock manage hooks
	    $this->loader->add_action('woocommerce_reduce_order_stock', $plugin_admin, 'transfer_contifico_stock');
	    $this->loader->add_action('woocommerce_restore_order_stock', $plugin_admin, 'restore_contifico_stock');
	    $this->loader->add_action('woocommerce_order_refunded', $plugin_admin, 'restore_contifico_stock', 10, 2);

        # Register Ajax hooks
	    $this->loader->add_action('wp_ajax_fetch_products', $plugin_admin, 'fetch_products');

	    # Add plugin crons
	    $this->loader->add_action('woo_contifico_sync_stock', $plugin_admin, 'batch_sync_processing', 10, 1);

	    # Check processing status to send invoice
	    $triggering_status = (isset($this->settings['etapa_envio'])) ? $this->settings['etapa_envio'] : 'processing';
	    if ($triggering_status == 'processing') {
		    $this->loader->add_action( 'woocommerce_order_status_processing', $plugin_admin, 'contifico_call', 10, 1 );
	    }
	    elseif ($triggering_status == 'complete') {
		    $this->loader->add_action( 'woocommerce_order_status_completed', $plugin_admin, 'contifico_call', 10, 1 );
	    }

	    # Hooks to display user fields in account and edit pages
	    $this->loader->add_action('show_user_profile', $plugin_admin, 'print_user_admin_fields', 30);
	    $this->loader->add_action('edit_user_profile', $plugin_admin, 'print_user_admin_fields', 30);

	    # Hooks to display and update tax info in order edit page
	    $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $plugin_admin, 'display_admin_order_meta', 10, 1);
	    $this->loader->add_action('woocommerce_process_shop_order_meta', $plugin_admin, 'save_order_meta_fields');

	    # Hooks to update user meta with Tax Id and Type (in checkout and account pages)
	    $this->loader->add_action('personal_options_update', $plugin_admin, 'save_account_fields');
	    $this->loader->add_action('edit_user_profile_update', $plugin_admin, 'save_account_fields');
	    $this->loader->add_action('woocommerce_save_account_details', $plugin_admin, 'save_account_fields');
	    $this->loader->add_action('woocommerce_created_customer', $plugin_admin, 'save_account_fields');

	    # Hooks to validate tax info
	    $this->loader->add_action('user_profile_update_errors', $plugin_admin, 'check_fields');
	    $this->loader->add_action('save_post_shop_order', $plugin_admin, 'order_check_fields');

	    # Hook to add an action to generate the invoice manually
	    $this->loader->add_action('woocommerce_order_actions', $plugin_admin, 'add_generate_invoice_box_action');
	    $this->loader->add_action('woocommerce_order_action_contifico_generate_invoice', $plugin_admin, 'generate_invoice_action');

    }

	/**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {

        $plugin_public = new Woo_Contifico_Public($this->get_plugin_name(), $this->get_version());

	    # Load style and script
	    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 99);
	    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

	    # Init hook
	    $this->loader->add_action('init', $plugin_public, 'init');

	    # Add checkout fields
	    $this->loader->add_filter('woocommerce_checkout_fields', $plugin_public, 'checkout_field');

	    # Populate checkout fields
	    $this->loader->add_filter('woocommerce_checkout_get_value', $plugin_public, 'populate_field', 10, 2);

	    # Check valid account data
	    $this->loader->add_action('woocommerce_checkout_process', $plugin_public, 'validate_user_account_data');
	    $this->loader->add_action('woocommerce_save_account_details_errors', $plugin_public, 'validate_user_account_data');

	    # Update order metadata
	    $this->loader->add_action('woocommerce_checkout_update_order_meta', $plugin_public, 'checkout_update_order_meta');

	    # Display tax fields on account page
	    $this->loader->add_action('woocommerce_edit_account_form', $plugin_public, 'print_user_frontend_fields');

    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     * @since     1.0.0
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    Woo_Contifico_Loader    Orchestrates the hooks of the plugin.
     * @since     1.0.0
     */
    public function get_loader()
    {
        return $this->loader;
    }

	/**
	 * Validate Cédula/RUC
	 *
	 * @since  2.0.0
	 *
	 * @param string $type Type of tax id
	 * @param string $data Data of tax id
	 * @throws exception
	 *
	 */
	public function validate_tax_id($type, $data)
	{
		# If type is cedula or ruc, validate (Pasaporte and Exterior does not need validation)
		if (in_array($type, [ 'cedula', 'ruc' ] )) {

			require_once WOO_CONTIFICO_PATH . 'libraries/validador-identificacion/validador.php';
			$validador = new Validador($this->plugin_name);

			# Check a cedula
			if ($type === 'cedula' && !$validador->validar_cedula($data)) {
				throw new Exception( sprintf( __( '<b>Cédula incorrecta:</b> %s', $this->plugin_name ), $validador->get_error() ) );
			}
			# Check a ruc
			elseif ($type === 'ruc' && !$validador->validar_ruc($data)) {
				throw new Exception( sprintf( __( 'RUC incorrecto: %s', $this->plugin_name ), $validador->get_error() ) );
			}
		}
	}

	/**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     * @since     1.0.0
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
	 * Get additional account fields.
	 *
	 * @param     bool      $required       Defines if fields are required or not. Default FALSE.
	 * @param     ?int   $user_id If set, uses the id to retrieve data, otherwise gets current user info
	 *
	 * @return    array
	 *@since     1.4.0
	 */
	public function get_account_fields($required = FALSE, $user_id = NULL) {

		# Check if user is logged in
		$customer_id = is_null($user_id) ? get_current_user_id() : $user_id;
		if ($customer_id > 0) {
			$taxpayer_type = esc_attr(get_user_meta($customer_id, "taxpayer_type", TRUE));
			$tax_subject = esc_attr(get_user_meta($customer_id, "tax_subject", TRUE));
			$tax_type = esc_attr(get_user_meta($customer_id, "tax_type", TRUE));
			$tax_id = esc_attr(get_user_meta($customer_id, "tax_id", TRUE));
		} else {
			$taxpayer_type = 'N';
			$tax_subject = 1;
			$tax_type = 'cedula';
			$tax_id = '';
		}

		return apply_filters('contifico_account_fields', [
			'taxpayer_type' => [
				'type' => 'radio',
				'label' => __('Tipo de contribuyente', $this->plugin_name),
				'default' => $taxpayer_type,
				'priority' => 23,
				'required' => $required,
				'options' => [
					'N' => __('Persona Natural', $this->plugin_name),
					'J' => __('Persona Jurídica', $this->plugin_name),
				]
			],
			'tax_subject' => [
				'type' => 'checkbox',
				'label' => __('¿Emitir el documento a nombre de la empresa?', $this->plugin_name),
				'value' => $tax_subject,
				'priority' => 29,
				'required' => false,
				'hide_in_account' => false,
			],
			'tax_type' => [
				'type' => 'select',
				'label' => __('Tipo de identificación', $this->plugin_name),
				'value' => $tax_type,
				'priority' => 24,
				'required' => $required,
				'options' => [
					'cedula' => __('Cédula', $this->plugin_name),
					'ruc' => __('RUC', $this->plugin_name),
					'pasaporte' => __('Pasaporte', $this->plugin_name),
					'exterior' => __('Identificación del exterior', $this->plugin_name)
				]
			],
			'tax_id' => [
				'type' => 'text',
				'label' => __('Número de identificación', $this->plugin_name),
				'value' => $tax_id,
				'priority' => 25,
				'required' => $required,
			],
		] );
	}

}
