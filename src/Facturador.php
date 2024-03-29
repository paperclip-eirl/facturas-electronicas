<?php
namespace Paperclip\FacturasElectrónicas;

use Exception;

/**
 * Clase para enviar la información de los comprobantes de pago via la API de
 * https://facturaselectronicas.biz/
 *
 * @author Oliver Etchebarne <yo@drmad.org>
 */
class Facturador
{
    private $token = '';
    private $url = '';

    private $parámetros = [];
    private $items = [];

    /** Respuesta de la API, para obtener con obtenerRespuesta() */
    private $respuesta = [];

    /** Opciones extra de cUrl */
    private $opciones_curl = [];

    /**
     * Constructor de la clase
     *
     * @param string $token Token de acceso
     * @param string $url URL para enviar la información.
     */
    public function __construct($token, $url)
    {
        // Validamos el token
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            throw new InvalidArgumentException("Formato de token inválido.");
        }

        $re = '/^https?\:\/\/.+[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/';
        if (!preg_match($re, $url)) {
            throw new InvalidArgumentException('URL de facturador mal formado.');
        }

        $this->token = $token;
        $this->url = $url;
    }

    /**
     * Añade opciones a la librería cURL. Usado usualmente para depuración.
     *
     * @param array $opciones Array de opciones, en el formato del comando
     *  curl_setopt_array()
     */
    public function opcionesCurl(array $opciones)
    {
        $this->opciones_curl = $opciones;
    }

    /**
     * Asigna parámetros a esta petición.
     * @param array $parámetros Parámetros a añadir
     * @return self
     */
    public function parámetros($parámetros)
    {
        $this->parámetros = $parámetros;
        return $this;
    }

    /**
     * Ejecuta un comando de la API.
     *
     * @param string $comando Comando a ejecutar.
     */
    public function ejecutar($comando)
    {

        $payload = json_encode($this->parámetros);

        $url = $this->url . '/' . $comando;

        $c = curl_init($url);
        curl_setopt($c, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $payload);

        if ($this->opciones_curl) {
            curl_setopt_array($c, $this->opciones_curl);
        }

        $result = curl_exec($c);

        if ($result === false) {
            throw new RuntimeException(curl_error($c));
        } else {
            $respuesta = json_decode($result, true);
            $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);

            // Esto _nunca_ debe pasar...
            if (is_null($respuesta)) {
                throw new ExcepciónFatal("Error interno de la API. Por favor, comuníquese con el administrador. [$code - $respuesta]");
            }

            $this->respuesta = $respuesta;

            // En desarrollo es posible que no existe 'descripcion_error', pero
            // si 'sunat_respuesta'. Le damos preferencia en caso de error
            $mensaje_error = '';
            if (key_exists('sunat_respuesta', $respuesta)) {
                $mensaje_error = "[SUNAT {$respuesta['sunat_respuesta']}] {$respuesta['sunat_descripcion']}";
            } elseif (key_exists('descripcion_error', $respuesta)) {
                $mensaje_error = $respuesta['descripcion_error'];
                if ($respuesta['descripcion_extra'] ?? false) {
                    $mensaje_error .= ' - ' . $respuesta['descripcion_extra'];
                };
            }

            // Errores fatales.
            if ($code >= 500) {
                throw new ExcepciónFatal("Error interno de la API. Por favor, comuníquese con el administrador.", $code);
            }

            // Errores de parámetros del comando
            if ($code == 400) {
                throw new ExcepciónParámetros($mensaje_error, $code);
            }

            // Errores de autorización
            if ($code == 403) {
                throw new ExcepciónAutorización($mensaje_error, $code);
            }

            // Errores de negociación
            if ($code == 406) {
                throw new ExcepciónNegociación($mensaje_error, $code);
            }

            // No debería haber otro error acá
            if ($code >= 401 && $code <= 499) {
                throw new ExcepciónFatal("Error inesperado. Por favor, comuníquese con el administrador. [{$code}]");
            }

        }

        curl_close($c);

        return $respuesta;
    }

    /**
     * Retorna la respuesta dada por el facturador.
     */
    public function obtenerRespuesta(): array
    {
        return $this->respuesta;
    }

    /**
     * Ejecuta el comando "emitir"
     */
    public function emitir($parámetros)
    {
        return $this->parámetros($parámetros)->ejecutar('emitir');
    }

    /**
     * Ejecuta el comando "baja"
     */
    public function baja(array $parámetros): array
    {
        return $this->parámetros($parámetros)->ejecutar('baja');
    }

    /**
     * Ejecuta el comando "correo"
     */
    public function correo(array $parámetros): array
    {
        return $this->parámetros($parámetros)->ejecutar('correo');
    }

    /**
     * Ejecuta el comando "consultar_ruc"
     */
    public function consultarRuc(array $parámetros): array
    {
        return $this->parámetros($parámetros)->ejecutar('consultar_ruc');
    }


    /**
     * Ejecuta el comando "hola"
     */
    public function hola(array $parámetros): array
    {
        return $this->parámetros($parámetros)->ejecutar('hola');
    }
}
