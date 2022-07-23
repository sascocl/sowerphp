<?php

/**
 * SowerPHP
 * Copyright (C) SowerPHP (http://sowerphp.org)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace sowerphp\core;

/**
 * Clase para escribir y recuperar datos desde una sesión
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-04-22
 */
class Model_Datasource_Session
{

    /**
     * Método que inicia la sesión
     * @param expires Minutos en que expirará la sesión
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2020-01-30
     */
    public static function start($expires = 30)
    {
        $Request = new Network_Request();
        $lifetime = $expires * 60;
        $session_name = 'sec_session_id';
        $path = $Request->base();
        $path = $path != '' ? $path : '/';
        $domain = $Request->header('X-Forwarded-Host');
        if (!$domain) {
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        }
        if (strpos($domain, ':')) {
            list($domain, $port) = explode(':', $domain);
        }
        $secure = isset($_SERVER['HTTPS']) ? true : false;
        $httponly = true;
        ini_set('session.use_only_cookies', true);
        ini_set('session.gc_maxlifetime', $lifetime <= 65535 ? $lifetime : 65535);
        session_name($session_name);
        session_start();
        setcookie(session_name(), session_id(), time()+$lifetime, $path, $domain, $secure, $httponly);
    }

    /**
     * Carga configuración del inicio de la sesión
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2014-04-22
     */
    public static function configure()
    {
        // idioma
        if (!self::read('config.language')) {
            $defaultLang = Configure::read('language');
            $userLang = (new Network_Request(false))->header('Accept-Language');
            if ($userLang) {
                $userLang = explode(',', explode('-', $userLang)[0])[0];
                if ($userLang === explode('_', $defaultLang)[0] || I18n::localeExists($userLang)) {
                    self::write('config.language', $userLang);
                } else {
                    self::write('config.language', $defaultLang);
                }
            } else {
                self::write('config.language', $defaultLang);
            }
        }
        // layout
        if (!self::read('config.page.layout')) {
            self::write('config.page.layout', Configure::read('page.layout'));
        }
    }

    /**
     * Entrega true si la variable esta creada en la sesión
     * @param name Nombre de la variable que se quiere buscar
     * @return Verdadero si la variable existe en la sesión
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2022-07-23
     */
    public static function check($name)
    {
        if (!isset($_SESSION)) {
            return false;
        }
        $result = Utility_Set::classicExtract($_SESSION, $name);
        return isset($result);
    }

    /**
     * Recuperar el valor de una variable de sesión
     * @param name Nombre de la variable que se desea leer
     * @return Valor de la variable o falso en caso que no exista o la sesión no este iniciada
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2022-07-23
     */
    public static function read($name = null)
    {
        if (!isset($_SESSION)) {
            return false;
        }
        // Si no se indico un nombre, se entrega todo el arreglo de la sesión
        if ($name === null) {
            return $_SESSION;
        }
        // Extraer los datos que se están solicitando
        $result = Utility_Set::classicExtract($_SESSION, $name);
        // Verificar que lo solicitado existe
        if (!isset($result)) {
            return false;
        }
        // Retornar lo solicitado (ya se reviso si existía, por lo cual si es null es válido el valor)
        return $result;
    }

    /**
     * Quitar una variable de la sesión
     * @param name Nombre de la variable que se desea eliminar
     * @return Verdadero si se logro eliminar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2022-07-23
     */
    public static function delete($name)
    {
        // Si la variable existe se quita
        if (self::check($name)) {
            self::_overwrite($_SESSION, Utility_Set::remove($_SESSION, $name));
            return (self::check($name) === false);
        }
        // En caso que no se encontrara la variable se retornará falso
        return false;
    }

    /**
     * Escribir un valor de una variable de sesión
     * @param name Nombre de la variable
     * @param value Valor que se desea asignar a la variable
     * @return Verdadero si se logró escribir la variable de sesión
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2022-07-23
     */
    public static function write($name, $value = null)
    {
        if (!isset($_SESSION)) {
            return false;
        }
        // Armar el arreglo necesario para realizar la escritura
        $write = $name;
        if (!is_array($name)) {
            $write = array($name => $value);
        }
        // Por cada elemento del arreglo escribir los datos de la sesión
        foreach ($write as $key => $val) {
            self::_overwrite($_SESSION, Utility_Set::insert($_SESSION, $key, $val));
            if (Utility_Set::classicExtract($_SESSION, $key) !== $val) {
                return false;
            }
        }
        return true;
    }

    /**
     * Used to write new data to _SESSION, since PHP doesn't like us setting the _SESSION var itself
     * @param old Antiguo conjunto de datos
     * @param new Nuevo conjunto de datos
     * @author CakePHP
     */
    protected static function _overwrite(&$old, $new)
    {
        if (!empty($old)) {
            foreach ($old as $key => $var) {
                if (!isset($new[$key])) {
                    unset($old[$key]);
                }
            }
        }
        foreach ($new as $key => $var) {
            $old[$key] = $var;
        }
    }

    /**
     * Método para destruir e invalidar una sesión
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2014-12-06
     */
    public static function destroy()
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Método para escribir un mensaje de sesión y recuperarlo
     * @param message Mensaje que se desea mostrar
     * @param type Tipo de mensaje: success, info, warning o danger
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2019-06-14
     */
    public static function message($message = null, $type = 'info')
    {
        // si se indicó un mensaje se asigna
        if ($message) {
            if ($type=='ok') {
                $type = 'success';
            }
            else if ($type=='error') {
                $type = 'danger';
            }
            $messages = self::message();
            $messages[] =  [
                'text' => $message,
                'type' => $type,
            ];
            self::write('session.messages', $messages);
        }
        // si no se indicó un mensaje se recupera y limpia
        else {
            $message = self::read('session.messages');
            self::delete('session.messages');
            return $message ? (array)$message : [];
        }
    }

}
