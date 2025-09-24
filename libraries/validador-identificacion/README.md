# Librería WordPress para validar Identificaciones en Ecuador

Librería para WordPress que valida identificaciones nacionales e internacionales para Ecuador, basado en el código de [Diaspar](https://github.com/diaspar/validacion-cedula-ruc-ecuador)

## Configurando la librería

Para configurar el SDK de licencia como una librería:

 1. Incluir la base de código de la librería
 2. Cargar la librería incluyendo el archivo validador.php

### 1. Incluir la base de código

El uso de un subárbol en su complemento para incluir el SDK de licencia es el método recomendado.

#### Paso 1. Agregar el repositorio como remoto

```
git remote add -f subtree-validador-identificacion https://github.com/otakupahp/validar-identificacion-ecuador.git
```

Agregar el subárbol como control remoto nos permite referirnos a él de forma breve a través del nombre subtree-validador-identificacion, en lugar de la URL completa de GitHub.

#### Paso 2. Agregar el repositorio como un subárbol

```
git subtree add --prefix librerías/validador-identificacion subtree-validador-identificacion master --squash
```

Esto agregará la rama maestra del validador a su repositorio en la carpeta librerías/validador-identificacion.

Se puede cambiar --prefix para apuntar donde se incluye el código.

#### Paso 3. Actualizar el subárbol

Para actualizar la librería a una nueva versión, se debe usar los siguientes comandos:

```
git fetch subtree-validador-identificacion master
git subtree pull --prefix librerías/validador-identificacion subtree-validador-identificacion master --squash
```

### 2. Cargando la librería

Independientemente de cómo se instale, para cargar la librería, solo se necesita incluir el archivo validador.php.

```
<?php
require_once (plugin_dir_path (__FILE__). '/librerías/validador-identificacion/validador.php
```

## Uso de la librería

Una vez cargada la librería se deberá crear una instancia de la misma para poder usarla. 

```
$validador = new Validador('mi-plugin');
```

La variable que se envía es el dominio de "text-domain" usado para internacionalizar los mensajes del plugin.

Para más información sobre internacionalización de plugins visitar la documentación de WordPress:

 * [Internationalization](https://developer.wordpress.org/themes/functionality/internationalization/)
 * [How to Internationalize Your Plugin](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)

Con la instancia de la librería lista, ya se puede validar las identificaciones

```
# validar CI
if ($validador->validar_cedula('0926687856')) {
    echo 'Cédula válida';
} else {
    echo 'Cédula incorrecta: '.$validador->getMessage();
}

# validar cualquier RUC
if ($validador->validar_ruc('0926687856001')) {
    echo 'RUC válido';
} else {
    echo 'RUC incorrecto: '.$validador->getMessage();
}

# validar RUC persona natural
if ($validador->validar_ruc_persona_natural('0926687856001')) {
    echo 'RUC válido';
} else {
    echo 'RUC incorrecto: '.$validador->getMessage();
}

# validar RUC sociedad privada
if ($validador->validar_ruc_sociedad_privada('0992397535001')) {
    echo 'RUC válido';
} else {
    echo 'RUC incorrecto: '.$validador->getMessage();
}

# validar RUC sociedad ublica
if ($validador->validar_ruc_sociedad_publica('1760001550001')) {
    echo 'RUC válido';
} else {
    echo 'RUC incorrecto: '.$validador->getMessage();
}
```

### Ejemplo: Función que comprueba si un valor dado es una cédula, RUC u otro documento

A continuación un ejemplo de una función que se puede usar para comprobar si la identificación dada es del tipo esperado.

```
/**
 * Validar si la identificación dada es una Cédula, RUC
 *
 * @param string $tipo  Tipo de documento a validar, los valores válidos son "cedula", "ruc"
 * @param string $valor Valor a ser evaluado
 
 * @throws exception    Emite un error si el valor no es del tipo esperado
 */
function validar_identificacion($tipo, $valor) {

    # Text domain del plugin usado para la internacionalización
    $plugin_name = 'mi-plugin'; 

    # Si el tipo es cédula o ruc, validar
    # cualquier otro se entiende como pasaporte o documento del exterior y no necesita validación 
    if (in_array($tipo, [ 'cedula', 'ruc' ] )) {

        require_once plugin_dir_path (__FILE__). '/librerías/validador-identificacion/validador.php';
        $validador = new Validador($plugin_name);

        # Verificar una cédula
        if ($tipo === 'cedula' && !$validador->validar_cedula($valor)) {
            throw new Exception( sprintf( __( 'Cédula incorrecta: %s', $plugin_name ), $validador->get_error() ) );
        }
        # Verificar un ruc
        elseif ($tipo === 'ruc' && !$validador->validar_ruc($valor)) {
            throw new Exception( sprintf( __( 'RUC incorrecto: %s', $plugin_name ), $validador->get_error() ) );
        }
    }
}

# Al llamar la función, se deberá comprobar si existe un mensaje de error o no
try {
    validar_identificacion('cedula', '0926687856');
    echo __( 'Identificación válida', 'mi-plugin' );
}
catch (Exception $exception) {
    echo sprintf( __( 'Identificación inválida > %s', 'mi-plugin' ), $exception->getMessage() );
}
```  
