<?php
/**
 * MIT License
 * ===========
 *
 * Copyright (c) 2012 Ing. Mauricio Lopez <mlopez@dixian.info>
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package     validador
 * @subpackage
 * @author      Ing. Mauricio Lopez <mlopez@dixian.info>
 * @copyright   2012 Ing. Mauricio Lopez (diaspar)
 * @license     http://www.opensource.org/licenses/mit-license.php  MIT License
 * @link        http://www.dixian.info
 * @version     @@0.8@@
 */

if( !class_exists('Validador') ) {

    /**
     * Validador contiene métodos para validar cédula, RUC de persona natural, RUC de sociedad privada y
     * RUC de sociedad pública en el Ecuador.
     *
     * Los métodos públicos para realizar validaciones son:
     *
     * validar_cedula()
     * validar_ruc()
     * validar_ruc_persona_natural()
     * validar_ruc_sociedad_privada()
     * validar_ruc_sociedad_publica()
     */
    final class Validador
    {

        /**
         * Error
         *
         * Contiene errores globales de la clase
         *
         * @access protected
         * @var string
         */
        private $error = '';

        /**
         * Texto usado para la internacionalización
         *
         * @access   protected
         * @var      string $text_domain
         */
        protected $text_domain;

        /**
         * Validador constructor.
         *
         * @param $text_domain
         */
        public function __construct($text_domain = 'wordpress') {
            $this->text_domain = $text_domain;
        }

        /**
         * Validar cédula
         *
         * @param string $numero Número de cédula
         *
         * @return Boolean
         */
        public function validar_cedula($numero = '')
        {
            # fuerzo parámetro de entrada a string
            $numero = (string)$numero;

            # borro por si acaso errores de llamadas anteriores.
            $this->set_error('');

            # validaciones
            try {
                $this->validar_inicial($numero, '10');
                $this->validar_codigo_provincia(substr($numero, 0, 2));
                $this->algoritmo_modulo_10(substr($numero, 0, 9), $numero[9]);
            }
            catch (Exception $exception) {
                $this->set_error($exception->getMessage());
                return false;
            }

            return true;
        }

	    /**
	     * Validar cualquier RUC
	     *
	     * @param string $numero Número de RUC
	     *
	     * @return Boolean
	     */
	    public function validar_ruc($numero = '')
	    {
	    	$result = (
	    		$this->validar_ruc_persona_natural($numero) ||
			    $this->validar_ruc_sociedad_privada( $numero ) ||
			    $this->validar_ruc_sociedad_publica( $numero ) );

		    if(!$result) {
			    $this->set_error(__('RUC inválido', $this->text_domain) );
		    }

		    return $result;
	    }

        /**
         * Validar RUC persona natural
         *
         * @param string $numero Número de RUC persona natural
         *
         * @return Boolean
         */
        public function validar_ruc_persona_natural($numero = '')
        {
            # fuerzo parámetro de entrada a string
            $numero = (string)$numero;

            # borro por si acaso errores de llamadas anteriores.
            $this->set_error('');

            # validaciones
            try {
                $this->validar_inicial($numero, '13');
                $this->validar_codigo_provincia(substr($numero, 0, 2));
                $this->validar_codigo_establecimiento(substr($numero, 10, 3));
                $this->algoritmo_modulo_10(substr($numero, 0, 9), $numero[9]);
            }
            catch (Exception $exception) {
                $this->set_error($exception->getMessage());
                return false;
            }

            return true;
        }

        /**
         * Validar RUC sociedad privada
         *
         * @param string $numero Número de RUC sociedad privada
         *
         * @return Boolean
         */
        public function validar_ruc_sociedad_privada($numero = '')
        {
            # fuerzo parámetro de entrada a string
            $numero = (string)$numero;

            # borro por si acaso errores de llamadas anteriores.
            $this->set_error('');

            # validaciones
            try {
                $this->validar_inicial($numero, '13');
                $this->validar_codigo_provincia(substr($numero, 0, 2));
                $this->validar_tercer_digito($numero[2], 'ruc_privada');
                $this->validar_codigo_establecimiento(substr($numero, 10, 3));
                $this->algoritmo_modulo_11(substr($numero, 0, 9), $numero[9], 'ruc_privada');
            }
            catch (Exception $exception) {
                $this->set_error($exception->getMessage());
                return false;
            }

            return true;
        }

        /**
         * Validar RUC sociedad publica
         *
         * @param string $numero Número de RUC sociedad publica
         *
         * @return Boolean
         */
        public function validar_ruc_sociedad_publica($numero = '')
        {
            # fuerzo parámetro de entrada a string
            $numero = (string)$numero;

            # borro por si acaso errores de llamadas anteriores.
            $this->set_error('');

            # validaciones
            try {
                $this->validar_inicial($numero, '13');
                $this->validar_codigo_provincia(substr($numero, 0, 2));
                $this->validar_tercer_digito($numero[2], 'ruc_publica');
                $this->validar_codigo_establecimiento(substr($numero, 9, 4));
                $this->algoritmo_modulo_11(substr($numero, 0, 8), $numero[8], 'ruc_publica');
            }
            catch (Exception $exception) {
                $this->set_error($exception->getMessage());
                return false;
            }

            return true;
        }

        /**
         * Validaciones iniciales para CI y RUC
         *
         * @param string $numero CI o RUC
         * @param int $caracteres Cantidad de caracteres requeridos
         *
         * @throws exception Cuando valor esta vacío, cuando no es dígito y
         * cuando no tiene cantidad requerida de caracteres
         */
        private function validar_inicial($numero, $caracteres)
        {
            if (empty($numero)) {
                throw new Exception( __('Valor no puede estar vacío', $this->text_domain) );
            }

            if (!ctype_digit($numero)) {
                throw new Exception( __('Valor ingresado solo puede tener dígitos', $this->text_domain) );
            }

            if (strlen($numero) != $caracteres) {
                throw new Exception( sprintf( __('Valor ingresado debe tener %d caracteres', $this->text_domain), $caracteres) );
            }

        }

        /**
         * Validación de código de provincia (dos primeros dígitos de CI/RUC)
         *
         * @param string $numero Dos primeros dígitos de CI/RUC
         *
         * @throws exception Cuando el código de provincia no esta entre 00 y 24
         */
        private function validar_codigo_provincia($numero)
        {
            if ($numero < 0 or $numero > 24) {
                throw new Exception( __('Codigo de Provincia (dos primeros dígitos) no deben ser mayor a 24 ni menores a 0', $this->text_domain) );
            }
        }

        /**
         * Validación de tercer dígito
         *
         * Permite validad el tercer dígito del documento. Dependiendo
         * del campo tipo (tipo de identificación) se realizan las validaciones.
         * Los posibles valores del campo tipo son: ruc_privada y ruc_publica
         *
         * Para RUC de sociedades privadas el tercer dígito debe ser
         * igual a 9.
         *
         * Para RUC de sociedades públicas el tercer dígito debe ser
         * igual a 6.
         *
         * @param string $numero tercer dígito de CI/RUC
         * @param string $tipo tipo de identificador
         *
         * @throws exception Cuando el tercer digito no es válido. El mensaje
         * de error depende del tipo de Identificación.
         */
        private function validar_tercer_digito($numero, $tipo)
        {
            if ($tipo == 'ruc_privada' && $numero != 9) {
                throw new Exception( __('Tercer dígito debe ser igual a 9 para sociedades privadas', $this->text_domain) );
            }
            elseif ($tipo == 'ruc_publica' && $numero != 6) {
                throw new Exception( __('Tercer dígito debe ser igual a 6 para sociedades públicas', $this->text_domain) );
            }
        }

        /**
         * Validación de código de establecimiento
         *
         * @param string $numero tercer dígito de CI/RUC
         *
         * @throws exception Cuando el establecimiento es menor a 1
         */
        private function validar_codigo_establecimiento($numero)
        {
            if ($numero < 1) {
                throw new Exception( __('Código de establecimiento no puede ser 0', $this->text_domain) );
            }
        }

        /**
         * Algoritmo Modulo10 para validar si CI y RUC de persona natural son válidos.
         *
         * Los coeficientes usados para verificar el décimo dígito de la cédula,
         * mediante el algoritmo “Módulo 10” son:  2. 1. 2. 1. 2. 1. 2. 1. 2
         *
         * Paso 1: Multiplicar cada dígito de los digitosIniciales por su respectivo
         * coeficiente.
         *
         *  Ejemplo
         *  digitosIniciales posición 1  x 2
         *  digitosIniciales posición 2  x 1
         *  digitosIniciales posición 3  x 2
         *  digitosIniciales posición 4  x 1
         *  digitosIniciales posición 5  x 2
         *  digitosIniciales posición 6  x 1
         *  digitosIniciales posición 7  x 2
         *  digitosIniciales posición 8  x 1
         *  digitosIniciales posición 9  x 2
         *
         * Paso 2: Sí alguno de los resultados de cada multiplicación es mayor a o igual a 10,
         * se suma entre ambos dígitos de dicho resultado. Ex. 12->1+2->3
         *
         * Paso 3: Se suman los resultados y se obtiene total
         *
         * Paso 4: Divido total para 10, se guarda residuo. Se resta 10 menos el residuo.
         * El valor obtenido debe concordar con el digitoVerificador
         *
         * Nota: Cuando el residuo es cero(0) el dígito verificador debe ser 0.
         *
         * @param string $digitos_iniciales Nueve primeros dígitos de CI/RUC
         * @param string $digito_verificador Décimo dígito de CI/RUC
         *
         * @throws exception Cuando los digitosIniciales no concuerdan contra
         * el código verificador.
         */
        private function algoritmo_modulo_10($digitos_iniciales, $digito_verificador) {
            $array_coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];

            $digito_verificador = (int)$digito_verificador;
            $digitos_iniciales = str_split($digitos_iniciales);

            $total = 0;
            foreach ($digitos_iniciales as $key => $value) {

                $valor_posicion = ((int)$value * $array_coeficientes[$key]);

                if ($valor_posicion >= 10) {
                    $valor_posicion = str_split($valor_posicion);
                    $valor_posicion = array_sum($valor_posicion);
                    $valor_posicion = (int)$valor_posicion;
                }

                $total = $total + $valor_posicion;
            }

            $residuo = $total % 10;

            if ($residuo == 0) {
                $resultado = 0;
            }
            else {
                $resultado = 10 - $residuo;
            }

            if ($resultado != $digito_verificador) {
                throw new Exception( __('Dígitos iniciales no validan contra Dígito Identificador', $this->text_domain) );
            }

        }

        /**
         * Algoritmo Modulo11 para validar RUC de sociedades privadas y públicas
         *
         * El código verificador es el décimo digito para RUC de empresas privadas
         * y el noveno dígito para RUC de empresas públicas
         *
         * Paso 1: Multiplicar cada dígito de los digitosIniciales por su respectivo
         * coeficiente.
         *
         * Para RUC privadas el coeficiente esta definido y se multiplica con las siguientes
         * posiciones del RUC:
         *
         *  Ejemplo
         *  digitosIniciales posición 1  x 4
         *  digitosIniciales posición 2  x 3
         *  digitosIniciales posición 3  x 2
         *  digitosIniciales posición 4  x 7
         *  digitosIniciales posición 5  x 6
         *  digitosIniciales posición 6  x 5
         *  digitosIniciales posición 7  x 4
         *  digitosIniciales posición 8  x 3
         *  digitosIniciales posición 9  x 2
         *
         * Para RUC privadas el coeficiente esta definido y se multiplica con las siguientes
         * posiciones del RUC:
         *
         *  digitosIniciales posición 1  x 3
         *  digitosIniciales posición 2  x 2
         *  digitosIniciales posición 3  x 7
         *  digitosIniciales posición 4  x 6
         *  digitosIniciales posición 5  x 5
         *  digitosIniciales posición 6  x 4
         *  digitosIniciales posición 7  x 3
         *  digitosIniciales posición 8  x 2
         *
         * Paso 2: Se suman los resultados y se obtiene total
         *
         * Paso 3: Divido total para 11, se guarda residuo. Se resta 11 menos el residuo.
         * El valor obtenido debe concordar con el digitoVerificador
         *
         * Nota: Cuando el residuo es cero(0) el dígito verificador debe ser 0.
         *
         * @param string $digitos_iniciales Nueve primeros dígitos de RUC
         * @param string $digito_verificador Décimo dígito de RUC
         * @param string $tipo Tipo de identificador
         *
         * @throws exception Cuando los digitosIniciales no concuerdan contra
         * el código verificador.
         */
        private function algoritmo_modulo_11($digitos_iniciales, $digito_verificador, $tipo)
        {
            switch ($tipo) {
                case 'ruc_privada':
                    $array_coeficientes = [4, 3, 2, 7, 6, 5, 4, 3, 2];
                    break;
                case 'ruc_publica':
                    $array_coeficientes = [3, 2, 7, 6, 5, 4, 3, 2];
                    break;
                default:
                    throw new Exception('Tipo de Identificación no existe.');
                    break;
            }

            $digito_verificador = (int)$digito_verificador;
            $digitos_iniciales = str_split($digitos_iniciales);

            $total = 0;
            foreach ($digitos_iniciales as $key => $value) {
                $valor_posicion = ((int)$value * $array_coeficientes[$key]);
                $total = $total + $valor_posicion;
            }

            $residuo = $total % 11;

            if ($residuo == 0) {
                $resultado = 0;
            }
            else {
                $resultado = 11 - $residuo;
            }

            if ($resultado != $digito_verificador) {
                throw new Exception( __('Dígitos iniciales no validan contra Dígito Identificador', $this->text_domain) );
            }
        }

        /**
         * Get error
         *
         * @return string Mensaje de error
         */
        public function get_error()
        {
            return $this->error;
        }

        /**
         * Set error
         *
         * @param string $newError
         * @return object $this
         */
        public function set_error($newError)
        {
            $this->error = $newError;
            return $this;
        }
    }

}
