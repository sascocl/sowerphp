<?php

/**
 * SowerPHP: Framework PHP hecho en Chile.
 * Copyright (C) SowerPHP <https://www.sowerphp.org>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero
 * de GNU publicada por la Fundación para el Software Libre, ya sea la
 * versión 3 de la Licencia, o (a su elección) cualquier versión
 * posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU
 * para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General
 * Affero de GNU junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace sowerphp\app;

use \sowerphp\core\Facade_Session_Message as SessionMessage;

/**
 * Componente para proveer de un sistema de autenticación y autorización
 */
class Controller_Component_Auth extends \sowerphp\core\Controller_Component
{

    public $settings = [ ///< Opciones por defecto
        'multipleLogins' => false,
        'maxLoginAttempts' => 3,
        'model' => '\sowerphp\app\Sistema\Usuarios\Model_Usuario',
        'session' => [
            'key' => 'session.auth',
            //'cache' => 1800, // 1800 segundos o 30 minutos (se saca de la configuración)
        ],
        'redirect' => [
            'login' => '/',
            'logout' => '/',
            'error' => '/',
            'form' => '/usuarios/ingresar',
        ],
        'messages' => [
            'ok' => [
                'login' => 'Usuario %s ha iniciado su sesión.',
                'lastlogin' => 'Último ingreso fue el %s desde %s.',
                'logout' => 'Usuario %s ha cerrado su sesión.',
            ],
            'error' => [
                'nologin' => 'Debe iniciar sesión para tratar de acceder a %s.',
                'auth' => 'Usuario %s no está autorizado para acceder a %s.',
                'invalid' => 'Usuario o contraseña inválida.',
                'notexist' => 'Usuario %s no existe.',
                'inactive' => 'Cuenta de usuario %s no se encuentra activa.',
                'newlogin' => 'Sesión cerrada. Usuario %s inició sesión en otro dispositivo.',
                'login_attempts_exceeded' => 'Cuenta de usuario %s fue bloqueada por exceder intentos de sesión, debe recuperar su contraseña.',
                'auth2' => 'Autenticación secundaria del usuario %s falló: %s',
                'recaptcha_invalid' => 'Captcha incorrecto para el usuario %s.',
            ],
        ],
    ];
    private $allowedActions = []; ///< Acciones sin login
    private $allowedActionsWithLogin = []; ///< Acciones con login
    private $session = null; ///< Información de la sesión del usuario
    public $User = false; ///< Usuario que se ha identificado en la sesión
    private $__logged = null;
    private $Cache; ///< Objeto para el caché

    /**
     * Método que inicializa el componente y carga la sesión activa
     */
    public function __construct(\sowerphp\core\Controller_Component_Collection $Components, $settings = [])
    {
        // asignar configuración de duración de la sesión (session.lifetime está en minutos)
        $this->settings['session']['cache'] = config('session.lifetime') * 60;
        // ejecutar el constructor padre
        parent::__construct($Components, $settings);
        // recuperar sesión
        $this->session = session($this->settings['session']['key']);
        // si hay sesión se obtiene el objeto del usuario
        if ($this->session) {
            $this->Cache = new \sowerphp\core\Cache();
            $this->User = $this->Cache->get(
                $this->settings['session']['key'] . $this->session['id']
            );
            if (!$this->User) {
                try {
                    $this->User = new $this->settings['model']($this->session['id']);
                } catch (\Exception $e) {
                    return;
                }
            }
            $this->User->groups();
            $this->User->auths();
            $this->saveCache();
        }
    }

    /**
     * Método que actualiza el usuario autenticado en la caché
     */
    public function saveCache()
    {
        $this->Cache->set(
            $this->settings['session']['key'] . $this->session['id'],
            $this->User,
            $this->settings['session']['cache']
        );
    }

    /**
     * Método que verifica si el usuario tiene permisos o bien da error
     * Wrapper para el método que hace la validación
     */
    public function boot(): void
    {
        if (!$this->isAuthorized()) {
            if (!$this->logged()) {
                SessionMessage::error(sprintf(
                    $this->settings['messages']['error']['nologin'],
                    $this->controller->request->getRequestUriDecoded()
                ));
                $this->controller->redirect(
                    $this->settings['redirect']['form'] . '/' .
                    base64_encode($this->controller->request->getRequestUriDecoded())
                );
            } else {
                $msg = sprintf(
                    $this->settings['messages']['error']['auth'],
                    $this->User->usuario,
                    $this->controller->request->getRequestUriDecoded()
                );
                SessionMessage::error($msg);
                $this->log($msg, LOG_ERR);
                $this->controller->redirect($this->settings['redirect']['error']);
            }
        }
    }

    /**
     * Agregar acciones que se permitirán ejecutar sin estár autenticado
     */
    public function allow($action = null)
    {
        $this->allowedActions = array_merge(
            $this->allowedActions, func_get_args()
        );
    }

    /**
     * Agregar acciones que se permitirán ejecutar a cualquier usuario que
     * esté autenticado
     */
    public function allowWithLogin($action = null)
    {
        $this->allowedActionsWithLogin = array_merge(
            $this->allowedActionsWithLogin, func_get_args()
        );
    }

    /**
     * Método que permite determinar si la acción solicitada del controlador
     * puede o no ser ejecutada sin estar con una sesión activa
     * @return bool =true si la acción del controlador se puede ejecutar sin tener sesión creada
     */
    public function allowedWithoutLogin($action = null)
    {
        if (!$action) {
            $action = $this->controller->request->getRouteConfig()['action'];
        }
        return in_array($action, $this->allowedActions);
    }

    /**
     * Método que permite determinar si la acción solicitada del controlador
     * puede o no ser ejecutada solo con tener una sesión activa
     * @return bool =true si la acción del controlador se puede ejecutar solo con tener sesión creada
     */
    public function allowedWithLogin($action = null)
    {
        if (!$action) {
            $action = $this->controller->request->getRouteConfig()['action'];
        }
        return in_array($action, $this->allowedActionsWithLogin);
    }

    /**
     * Método para determinar si un usuario está o no autorizado a un área
     */
    public function isAuthorized()
    {
        // Si la acción se encuentra dentro de las permitidas dejar pasar
        if (in_array($this->controller->request->getRouteConfig()['action'], $this->allowedActions)) {
            return true;
        }
        // si el usuario no existe en la sesión se retorna falso
        if (!$this->logged()) {
            return false;
        }
        // si la acción se encuentra dentro de las que solo requieren un
        // usuario logueado se acepta
        if (in_array($this->controller->request->getRouteConfig()['action'], $this->allowedActionsWithLogin)) {
            return true;
        }
        // Chequear permisos
        return $this->check($this->controller->request);
    }

    /**
     * Indica si existe una sesión de un usuario creada
     */
    public function logged(): bool
    {
        if ($this->__logged === null) {
            if ($this->session && $this->User) {
                if (!$this->User->checkLastLoginHash($this->session['hash'])) {
                    (new \sowerphp\core\Cache())->delete(
                        $this->settings['session']['key'].$this->session['id']
                    );
                    session()->flush();
                    SessionMessage::error(sprintf(
                        $this->settings['messages']['error']['newlogin'],
                        $this->User->usuario
                    ));
                    $this->__logged = false;
                } else {
                    $this->__logged = true;
                }
            } else {
                $this->__logged = false;
            }
        }
        return $this->__logged;
    }

    /**
     * Método que revisa si hay o no permisos para determinado recurso y cierto
     * usuario (por defecto la web que se trata de acceder y el usuario
     * autenticado).
     */
    public function check($recurso = false, $usuario = null)
    {
        if (!$recurso) {
            $recurso = $this->controller->request->getRequestUriDecoded();
        }
        if ($usuario) {
            return (new $this->settings['model']($usuario))->auth($recurso);
        } else {
            return $this->User ? $this->User->auth($recurso) : false;
        }
    }

    /**
     * Método que realiza el login del usuario
     */
    public function login($usuario, $contrasenia, $auth2_token = null)
    {
        // crear objeto del usuario con el nombre de usuario entregado
        try {
            $this->User = new $this->settings['model']($usuario);
        } catch (\sowerphp\core\Exception_Database $e) {
            $this->User = new $this->settings['model']();
        }
        // si el usuario no existe -> error
        if (!$this->User->exists()) {
            SessionMessage::error(sprintf(
                $this->settings['messages']['error']['notexist'],
                $usuario
            ));
            if (isset($this->settings['redirect']['notexist'])) {
                $this->controller->redirect($this->settings['redirect']['notexist']);
            } else {
                return;
            }
        }
        // si el usuario no está activo -> error
        if (!$this->User->isActive()) {
            SessionMessage::error(sprintf(
                $this->settings['messages']['error']['inactive'],
                $usuario
            ));
            return;
        }
        // si la cuenta ya no tienen intentos de login -> error
        if (!$this->User->contrasenia_intentos) {
            SessionMessage::error(sprintf(
                $this->settings['messages']['error']['login_attempts_exceeded'],
                $usuario
            ));
            return;
        }
        // si ya hubo un intento de login fallido entonces se pedirá captcha
        if ($this->settings['maxLoginAttempts'] && $this->User->contrasenia_intentos<$this->settings['maxLoginAttempts']) {
            try {
                \sowerphp\general\Utility_Google_Recaptcha::check();
            } catch (\Exception $e) {
                SessionMessage::error(sprintf(
                    $this->settings['messages']['error']['recaptcha_invalid'],
                    $usuario
                ) . ' ' . $e->getMessage());
                return;
            }
        }
        // si la contraseña no es correcta -> error
        if (!$this->User->checkPassword($contrasenia)) {
            if ($this->settings['maxLoginAttempts']) {
                $this->User->savePasswordRetry($this->User->contrasenia_intentos - 1);
            }
            if ($this->User->contrasenia_intentos) {
                $msg = $this->settings['messages']['error']['invalid'];
                SessionMessage::error($msg);
            } else {
                $msg = sprintf($this->settings['messages']['error']['login_attempts_exceeded'], $usuario);
                SessionMessage::error($msg);
            }
            $this->log($msg, LOG_ERR);
            return;
        }
        // verificar token en sistema secundario de autorización
        try {
            $this->User->checkAuth2($auth2_token);
        } catch (\Exception $e) {
            SessionMessage::error(sprintf(
                $this->settings['messages']['error']['auth2'],
                $usuario,
                $e->getMessage()
            ));
            return;
        }
        // si se pasaron toda las validaciones anteriores -> crear sesión
        // registrar ingreso en la base de datos
        $lastLogin = $this->User->lastLogin();
        if (isset($lastLogin['fecha_hora'][0])) {
            $lastlogin = '. ' . sprintf(
                $this->settings['messages']['ok']['lastlogin'],
                \sowerphp\general\Utility_Date::format($lastLogin['fecha_hora'], 'd/m/Y H:i'),
                $lastLogin['desde']
            );
        } else {
            $lastlogin = '';
        }
        $this->createSession();
        // mensaje para mostrar
        $msg = sprintf(
            $this->settings['messages']['ok']['login'],
            $this->User->usuario
        ) . $lastlogin;
        SessionMessage::success($msg);
        $this->log($msg);
        // si el usuario tiene layout personalizado se asigna
        if ($this->User->config_app_ui_layout) {
            session(['config.app.ui.layout' => $this->User->config_app_ui_layout]);
        }
        // redireccionar
        if (isset($_POST['redirect'][0])) {
            $this->controller->redirect($_POST['redirect']);
        } else {
            $this->controller->redirect($this->settings['redirect']['login']);
        }
    }

    /**
     * Método que crea la sesión del usuario registrado en la autenticación
     */
    private function createSession()
    {
        $hash = $this->User->updateLastLogin($this->ip(true), $this->settings['multipleLogins']);
        if ($this->settings['maxLoginAttempts']) {
            $this->User->savePasswordRetry($this->settings['maxLoginAttempts']);
        }
        $this->session = [
            'id' => $this->User->id,
            'hash' => $hash,
        ];
        session([$this->settings['session']['key'] => $this->session]);
    }

    /**
     * Método que realiza el login del usuario a través de preautenticación
     */
    public function preauth($token, $usuario = null, $auth2_token = null)
    {
        // autenticar solo con token (este será el hash del usuario)
        if (!$usuario) {
            $this->User = new $this->settings['model']($token);
        }
        // autenticar con los datos del token
        else {
            $key = config('app.key');
            if (!$key) {
                return false;
            }
            $real_token = md5($usuario . date('Ymd') . $key);
            if ($token != $real_token) {
                return false;
            }
            $this->User = new $this->settings['model']($usuario);
        }
        // si el usuario no existe error
        if (!$this->User->exists() || !$this->User->isActive()) {
            return false;
        }
        // verificar token de autenticación secundaria
        try {
            $this->User->checkAuth2($auth2_token);
        } catch (\Exception $e) {
            return false;
        }
        // crear sesión
        $this->createSession();
        return true;
    }

    /**
     * Método que termina la sesión del usuario.
     */
    public function logout()
    {
        (new \sowerphp\core\Cache())->delete($this->settings['session']['key'].$this->session['id']);
        session()->flush();
        SessionMessage::success(sprintf(
            $this->settings['messages']['ok']['logout'],
            $this->User->usuario
        ));
        $this->controller->redirect($this->settings['redirect']['logout']);
    }

    /**
     * Establecer ip del visitante
     * @return string Ip del visitante
     */
    public function ip($get_from_proxy = false)
    {
        if ($get_from_proxy && getenv('HTTP_X_FORWARDED_FOR')) {
            $ips = explode(', ', getenv('HTTP_X_FORWARDED_FOR'));
            return $ips[count($ips) - 1];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Establecer host del visitante
     * @return string Host del visitante
     */
    public function host($get_from_proxy = false)
    {
        return gethostbyaddr($this->ip($get_from_proxy));
    }

    /**
     * Obtiene la ubicación del usuario que accede al sitio, si está disponible
     * GeoIP localmente en el servidor lo utilizará en caso contrario usará
     * el servicio https://freegeoip.net (máximo de 10.000 consultas por hora)
     * @return array Arreglo con los datos de la ubicación del usuario
     */
    public function location()
    {
        $ip = $this->ip();
        if (function_exists('geoip_record_by_name')) {
            $location = @geoip_record_by_name($ip);
        }
        if (!isset($location)) {
            $response = \sowerphp\core\Network_Http_Socket::get(
                'https://freegeoip.net/json/' . $ip
            );
            $location = $response['status']['code'] == 200
                ? (array)json_decode($response['body']) : false;
        }
        return $location;
    }

    /**
     * Método que guarda un evento en el log
     * @param message Mensaje del evento
     * @param severity Gravedad del evento
     */
    protected function log($message, $severity = LOG_INFO)
    {
        if (isset($this->controller->Log)) {
            $this->controller->Log->write($message, $severity, LOG_AUTH);
        }
    }

}
