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

namespace sowerphp\app\Sistema\Usuarios;

use \sowerphp\core\Facade_Session_Message as SessionMessage;

/**
 * Clase para el controlador asociado a la tabla usuario de la base de
 * datos.
 * Comentario de la tabla: Usuarios de la aplicación
 * Esta clase permite controlar las acciones entre el modelo y vista para la
 * tabla usuario.
 */
class Controller_Usuarios extends \sowerphp\app\Controller_Maintainer
{

    protected $namespace = __NAMESPACE__; ///< Namespace del controlador y modelos asociados
    protected $columnsView = [
        'listar'=>['id', 'nombre', 'usuario', 'email', 'activo', 'ultimo_ingreso_fecha_hora']
    ]; ///< Columnas que se deben mostrar en las vistas
    protected $deleteRecord = false; ///< Indica si se permite o no borrar registros
    protected $changeUsername = true; ///< Indica si se permite que se cambie el nombre de usuario

    /**
     * Permitir ciertas acciones y luego ejecutar verificar permisos con
     * parent::boot()
     */
    public function boot(): void
    {
        $this->Auth->allow('ingresar', 'salir', 'contrasenia_recuperar', 'registrar', 'preauth', '_api_perfil_GET');
        $this->Auth->allowWithLogin('perfil', 'telegram_parear');
        parent::boot();
    }

    /**
     * Acción para que un usuario ingrese al sistema (inicie sesión)
     * @param redirect Ruta (en base64) de hacia donde hay que redireccionar una vez se autentica el usuario
     */
    public function ingresar($redirect = null)
    {
        // si ya está logueado se redirecciona
        if ($this->Auth->logged()) {
            $this->redirect($this->Auth->settings['redirect']['login']);
        }
        // asignar variables para la vista
        $this->layout .= '.min';
        $this->set([
            'redirect' => $redirect ? base64_decode ($redirect) : null,
            'self_register' => (bool)config('app.users.self_register.enabled'),
            'language' => config('app.locale'),
            'auth2_token_enabled' => \sowerphp\app\Model_Datasource_Auth2::tokenEnabled(),
        ]);
        // procesar inicio de sesión
        if (isset($_POST['usuario'])) {
            // si el usuario o contraseña es vacio mensaje de error
            if (empty($_POST['usuario']) || empty($_POST['contrasenia'])) {
                SessionMessage::warning('Debe especificar usuario y clave.');
            }
            // realizar proceso de validación de datos
            else {
                $auth2_token = !empty($_POST['auth2_token']) ? $_POST['auth2_token'] : null;
                $this->Auth->login($_POST['usuario'], $_POST['contrasenia'], $auth2_token);
            }
        }
    }

    /**
     * Acción para que un usuario cierra la sesión
     */
    public function salir()
    {
        if ($this->Auth->logged()) {
            $this->Auth->logout();
        } else {
            SessionMessage::warning('No existe sesión de usuario abierta.');
            $this->redirect('/');
        }
    }

     /**
     * Acción que fuerza el cierre de sesión de un usuario eliminando su hash
     */
    public function salir_forzar($id)
    {
        $class = $this->Auth->settings['model'];
        $Usuario = new $class($id);
        if(!$Usuario->exists()) {
            SessionMessage::error(
                'Usuario no existe, no se puede forzar el cierre de la sesión.'
            );
            $this->redirect('/sistema/usuarios/usuarios/listar');
        }
        $Usuario->ultimo_ingreso_hash = null;
        try {
            $Usuario->save();
            (new \sowerphp\core\Cache())->delete($this->Auth->settings['session']['key'] . $id);
            SessionMessage::success(__(
                'Sesión del usuario %s cerrada.',
                $Usuario->usuario
            ));
        } catch (\Exception $e) {
            SessionMessage::error(__(
                'No fue posible forzar el cierre de la sesión: %s',
                $e->getMessage()
            ));
        }
        $this->redirect('/sistema/usuarios/usuarios/editar/'.$id);
    }

    /**
     * Acción para recuperar la contraseña
     * @param usuario Usuario al que se desea recuperar su contraseña
     */
    public function contrasenia_recuperar($usuario = null, $codigo = null)
    {
        $this->layout .= '.min';
        $class = $this->Auth->settings['model'];
        // pedir correo
        if ($usuario == null) {
            if (!isset($_POST['id'])) {
                return $this->render('Usuarios/contrasenia_recuperar_step1');
            } else {
                // validar captcha
                try {
                    \sowerphp\general\Utility_Google_Recaptcha::check();
                } catch (\Exception $e) {
                    SessionMessage::error(__(
                        'Falló validación captcha: %s',
                        $e->getMessage()
                    ));
                    $this->redirect($this->request->getRequestUriDecoded());
                }
                // buscar usuario y solicitar correo de recuperación
                try {
                    $Usuario = new $class($_POST['id']);
                } catch (\sowerphp\core\Exception_Database $e) {
                    $Usuario = new $class();
                }
                if (!$Usuario->exists()) {
                    SessionMessage::error(
                        'Usuario no válido. Recuerda que puedes buscar por tu nombre de usuario o correo.'
                    );
                    return $this->render('Usuarios/contrasenia_recuperar_step1');
                }
                else if (!$Usuario->activo) {
                    SessionMessage::error(
                        'Usuario no activo. Primero deberás realizar la activación del usuario, luego podrás cambiar la contraseña.'
                    );
                    return $this->render('Usuarios/contrasenia_recuperar_step1');
                }
                else {
                    $this->contrasenia_recuperar_email(
                        $Usuario->email,
                        $Usuario->nombre,
                        $Usuario->usuario,
                        md5(hash('sha256', $Usuario->contrasenia))
                    );
                    SessionMessage::success(
                        'Se ha enviado un email con las instrucciones para recuperar tu contraseña.'
                    );
                    $this->redirect('/usuarios/ingresar');
                }
            }
        }
        // cambiar contraseña
        else {
            // buscar usuario al que se desea cambiar la contraseña
            $Usuario = new $class(urldecode($usuario));
            if (!$Usuario->exists()) {
                SessionMessage::error('Usuario inválido.');
                $this->redirect('/usuarios/contrasenia/recuperar');
            }
            // formulario de cambio de contraseña
            if (!isset($_POST['contrasenia1'])) {
                $this->set([
                    'usuario' => $Usuario->usuario,
                    'codigo' => $codigo,
                ]);
                return $this->render('Usuarios/contrasenia_recuperar_step2');
            }
            // procesar cambio de contraseña
            else {
                // validar captcha
                try {
                    \sowerphp\general\Utility_Google_Recaptcha::check();
                } catch (\Exception $e) {
                    SessionMessage::error(__(
                        'Falló validación captcha: %s',
                        $e->getMessage()
                    ));
                    $this->redirect($this->request->getRequestUriDecoded());
                }
                // cambiar la contraseña al usuario
                if ($_POST['codigo'] != md5(hash('sha256', $Usuario->contrasenia))) {
                    SessionMessage::error(
                        'El enlace para recuperar su contraseña no es válido, solicite uno nuevo por favor.'
                    );
                    $this->redirect('/usuarios/contrasenia/recuperar');
                }
                else if (
                    empty ($_POST['contrasenia1'])
                    || empty ($_POST['contrasenia2'])
                    || $_POST['contrasenia1'] != $_POST['contrasenia2']
                ) {
                    SessionMessage::warning(
                        'Contraseña nueva inválida (en blanco o no coinciden).'
                    );
                    $this->set('usuario', $usuario);
                    return $this->render('Usuarios/contrasenia_recuperar_step2');
                }
                else {
                    $Usuario->savePassword($_POST['contrasenia1']);
                    $Usuario->savePasswordRetry($this->Auth->settings['maxLoginAttempts']);
                    SessionMessage::success(__(
                        'La contraseña para el usuario %s ha sido cambiada con éxito.',
                        $usuario
                    ));
                    $this->redirect('/usuarios/ingresar');
                }
            }
        }
    }

    /**
     * Método que envía el correo con los datos para poder recuperar la contraseña
     * @param correo Donde enviar el email
     * @param nombre Nombre "real" del usuario
     * @param usuario Nombre de usuario
     * @param hash Hash para identificar que el usuario es quien dice ser y cambiar su contraseña
     */
    private function contrasenia_recuperar_email($correo, $nombre, $usuario, $hash)
    {
        $this->layout = null;
        $this->set([
            'nombre' => $nombre,
            'usuario' => $usuario,
            'hash' => $hash,
            'ip' => $this->Auth->ip(),
        ]);
        $msg = $this->render('Usuarios/contrasenia_recuperar_email')->body();
        $email = new \sowerphp\core\Network_Email();
        $email->to($correo);
        $email->subject('Recuperación de contraseña');
        $status = $email->send($msg);
        if ($status !== true && $status['type'] == 'error') {
            SessionMessage::error($status['message']);
            $this->redirect('/usuarios/contrasenia/recuperar');
        }
    }

    /**
     * Acción para crear un nuevo usuario
     */
    public function crear()
    {
        if (!empty($_GET['listar'])) {
            $filterListarUrl = '?listar='.$_GET['listar'];
            $filterListar = base64_decode($_GET['listar']);
        } else {
            $filterListarUrl = '';
            $filterListar = '';
        }
        $class = $this->Auth->settings['model'];
        // si se envió el formulario se procesa
        if (isset($_POST['submit'])) {
            $Usuario = new $class();
            $Usuario->set($_POST);
            $Usuario->usuario = \sowerphp\core\Utility_String::normalize($Usuario->usuario);
            $Usuario->email = mb_strtolower($Usuario->email);
            $ok = true;
            if ($Usuario->checkIfUserAlreadyExists()) {
                SessionMessage::warning(
                    'Nombre de usuario %s ya está en uso.',
                    $Usuario->usuario
                );
                $ok = false;
            }
            if ($ok && $Usuario->checkIfHashAlreadyExists()) {
                SessionMessage::warning('Hash seleccionado ya está en uso.');
                $ok = false;
            }
            if ($ok && $Usuario->checkIfEmailAlreadyExists()) {
                SessionMessage::warning(__(
                    'Correo electrónico %s ya está en uso.',
                    $Usuario->email
                ));
                $ok = false;
            }
            if ($ok) {
                if (empty($Usuario->contrasenia)) {
                    $Usuario->contrasenia = \sowerphp\core\Utility_String::random(8);
                }
                $contrasenia = $Usuario->contrasenia;
                $Usuario->contrasenia = $Usuario->hashPassword($Usuario->contrasenia);
                if (empty($Usuario->hash)) {
                    do {
                        $Usuario->hash = \sowerphp\core\Utility_String::random(32);
                    } while ($Usuario->checkIfHashAlreadyExists ());
                }
                if ($Usuario->save()) {
                    $Usuario->saveGroups($_POST['grupos']);
                    if (empty($_POST['contrasenia'])) {
                        if ($Usuario->getEmailAccount()) {
                            $contrasenia = 'actual contraseña de correo '.$Usuario->getEmailAccount()->getEmail();
                        } else if ($Usuario->getLdapPerson()) {
                            $contrasenia = 'actual contraseña de cuenta '.$Usuario->getLdapPerson()->uid.' en LDAP';
                        }
                    } else {
                        $Usuario->savePassword($contrasenia);
                    }
                    // enviar correo
                    $emailConfig = config('email.default');
                    if (!empty($emailConfig['type']) && !empty($emailConfig['from'])) {
                        $layout = $this->layout;
                        $this->layout = null;
                        $this->set(array(
                            'nombre'=>$Usuario->nombre,
                            'usuario'=>$Usuario->usuario,
                            'contrasenia'=>$contrasenia,
                        ));
                        $msg = $this->render('Usuarios/crear_email')->body();
                        $this->layout = $layout;
                        $email = new \sowerphp\core\Network_Email();
                        $email->to($Usuario->email);
                        $email->subject('Cuenta de usuario creada');
                        $email->send($msg);
                        SessionMessage::success(__(
                            'Registro creado. Se envió email a %s con los datos de acceso.',
                            $Usuario->email
                        ));
                    } else {
                        SessionMessage::warning(
                            'Registro creado. No se pudo enviar el correo.'
                        );
                    }
                } else {
                    SessionMessage::error(
                        'Registro no creado (hubo algún error).'
                    );
                }
                $this->redirect('/sistema/usuarios/usuarios/listar' . $filterListar);
            }
        }
        // setear variables
        $class::$columnsInfo['contrasenia']['null'] = true;
        $class::$columnsInfo['hash']['null'] = true;
        $this->set(array(
            'accion' => 'Crear',
            'columns' => $class::$columnsInfo,
            'grupos_asignados' => (isset($_POST['grupos']) ? $_POST['grupos'] : []),
            'listarUrl' => '/sistema/usuarios/usuarios/listar' . $filterListar,
            'ldap' => config('ldap.default'),
        ));
        $this->setGruposAsignables();
        return $this->render('Usuarios/crear_editar');
    }

    /**
     * Acción para editar un nuevo usuario.
     */
    public function editar($id)
    {
        if (!empty($_GET['listar'])) {
            $filterListarUrl = '?listar=' . $_GET['listar'];
            $filterListar = base64_decode($_GET['listar']);
        } else {
            $filterListarUrl = '';
            $filterListar = '';
        }
        if (strpos($filterListar, 'http') === 0) {
            $redirect = $filterListar;
        } else {
            $redirect = '/sistema/usuarios/usuarios/listar' . $filterListar;
        }
        $class = $this->Auth->settings['model'];
        $Usuario = new $class($id);
        // si el registro que se quiere editar no existe error
        if(!$Usuario->exists()) {
            SessionMessage::error(
                'Registro ('.implode(', ', func_get_args()).') no existe, no se puede editar.'
            );
            $this->redirect($redirect);
        }
        // si no se ha enviado el formulario se mostrará
        if(!isset($_POST['submit'])) {
            $class::$columnsInfo['contrasenia']['null'] = true;
            $grupos_asignados = $Usuario->groups();
            $this->setGruposAsignables();
            $this->set(array(
                'accion' => 'Editar',
                'Obj' => $Usuario,
                'columns' => $class::$columnsInfo,
                'grupos_asignados' => array_keys($grupos_asignados),
                'listarUrl' => $redirect,
                'ldap' => config('ldap.default'),
            ));
            return $this->render('Usuarios/crear_editar');
        }
        // si se envió el formulario se procesa
        else {
            if (isset($_POST['usuario']) && !$this->changeUsername && $Usuario->usuario != $_POST['usuario']) {
                SessionMessage::warning(
                    'Nombre de usuario no puede ser cambiado.'
                );
                $this->redirect('/sistema/usuarios/usuarios/editar/' . $id . $filterListarUrl);
            }
            $activo = $Usuario->activo;
            $Usuario->set($_POST);
            $Usuario->usuario = \sowerphp\core\Utility_String::normalize($Usuario->usuario);
            $Usuario->email = mb_strtolower($Usuario->email);
            if ($Usuario->checkIfUserAlreadyExists()) {
                SessionMessage::warning(
                    'Nombre de usuario '.$Usuario->usuario.' ya está en uso.'
                );
                $this->redirect('/sistema/usuarios/usuarios/editar/'.$id.$filterListarUrl);
            }
            if ($Usuario->checkIfHashAlreadyExists ()) {
                SessionMessage::warning('Hash seleccionado ya está en uso.');
                $this->redirect('/sistema/usuarios/usuarios/editar/'.$id.$filterListarUrl);
            }
            if ($Usuario->checkIfEmailAlreadyExists ()) {
                SessionMessage::warning('Email seleccionado ya está en uso.');
                $this->redirect('/sistema/usuarios/usuarios/editar/'.$id.$filterListarUrl);
            }
            $Usuario->save();
            // enviar correo solo si el usuario estaba inactivo y ahora está activo
            if (!$activo && $Usuario->activo) {
                $emailConfig = config('email.default');
                if (
                    !empty($emailConfig['type'])
                    && !empty($emailConfig['user'])
                    && !empty($emailConfig['pass'])
                ) {
                    $layout = $this->layout;
                    $this->layout = null;
                    $this->set([
                        'nombre' => $Usuario->nombre,
                        'usuario' => $Usuario->usuario,
                    ]);
                    $msg = $this->render('Usuarios/activo_email')->body();
                    $this->layout = $layout;
                    $email = new \sowerphp\core\Network_Email();
                    $email->to($Usuario->email);
                    $email->subject('Cuenta de usuario habilitada');
                    $email->send($msg);
                }
            }
            if (!empty($_POST['contrasenia'])) {
                $Usuario->savePassword($_POST['contrasenia']);
                $Usuario->savePasswordRetry($this->Auth->settings['maxLoginAttempts']);
            }
            $Usuario->saveGroups($_POST['grupos']);
            SessionMessage::success(
                'Registro Usuario('.implode(', ', func_get_args()).') editado.'
            );
            $this->redirect($redirect);
        }
    }

    /**
     * Método que asigna los grupos que el usuario logueado puede asignar al
     * crear o editar un usuario.
     */
    private function setGruposAsignables()
    {
        $grupos = (new Model_Grupos())->getList();
        // si el usuario no pertenece al grupo sysadmin quitar los grupos
        // sysadmin y appadmin del listado para evitar que los asignen
        if (!$this->Auth->User->inGroup('sysadmin')) {
            $prohibidos = ['sysadmin', 'appadmin', 'passwd', 'soporte', 'mantenedores', 'webservices'];
            $aux = $grupos;
            $grupos = [];
            foreach ($aux as $key => &$grupo) {
                if (!in_array($grupo['glosa'], $prohibidos)) {
                    $grupos[] = $grupo;
                }
            }
            unset ($aux);
        }
        $this->set('grupos', $grupos);
    }

    /**
     * Acción para mostrar y editar el perfil del usuario que esta autenticado.
     */
    public function perfil()
    {
        // si hay cualquier campo que empiece por 'config_' se quita ya que son
        // configuraciones reservadas para los administradores de la APP y no
        // pueden ser asignadas por los usuarios (esto evita que envién
        // "a la mala" una configuración). Si se desea que el usuario pueda
        // configurar alguna configuración personalizada en el perfil del usuario, se deberá enviar a una
        // acción diferente en un Controlador de usuarios personalizado (que herede este)
        foreach ($_POST as $var => $val) {
            if (strpos($var, 'config_') === 0) {
                unset($_POST[$var]);
            }
        }
        // procesar datos personales
        if (isset($_POST['datosUsuario'])) {
            // actualizar datos generales
            if (
                isset($_POST['usuario'])
                && !$this->changeUsername
                && $this->Auth->User->usuario != $_POST['usuario']
            ) {
                SessionMessage::error('Nombre de usuario no puede ser cambiado.');
                $this->redirect('/usuarios/perfil');
            }
            $this->Auth->User->nombre = $_POST['nombre'];
            if ($this->changeUsername && !empty($_POST['usuario'])) {
                $this->Auth->User->usuario = \sowerphp\core\Utility_String::normalize($_POST['usuario']);
            }
            $this->Auth->User->email = mb_strtolower($_POST['email']);
            if (isset($_POST['hash'])) {
                $this->Auth->User->hash = $_POST['hash'];
                if (
                    !empty($this->Auth->User->hash)
                    && strlen($this->Auth->User->hash) != 32
                ) {
                    SessionMessage::error(
                        'Hash del usuario debe ser de largo 32.'
                    );
                    $this->redirect('/usuarios/perfil');
                }
            }
            if ($this->Auth->User->checkIfUserAlreadyExists()) {
                SessionMessage::error(__(
                    'Nombre de usuario %s ya está en uso.',
                    $this->Auth->User->usuario
                ));
                $this->redirect('/usuarios/perfil');
            }
            if ($this->Auth->User->checkIfHashAlreadyExists()) {
                SessionMessage::error('Hash seleccionado ya está en uso.');
                $this->redirect('/usuarios/perfil');
            }
            if ($this->Auth->User->checkIfEmailAlreadyExists()) {
                SessionMessage::error(__(
                    'Correo electrónico %s ya está en uso.',
                    $this->Auth->User->email
                ));
                $this->redirect('/usuarios/perfil');
            }
            if (empty($this->Auth->User->hash)) {
                do {
                    $this->Auth->User->hash = \sowerphp\core\Utility_String::random(32);
                } while ($this->Auth->User->checkIfHashAlreadyExists());
            }
            $this->Auth->User->save();
            $this->Auth->saveCache();
            // mensaje de ok y redireccionar
            SessionMessage::success('Perfil de usuario actualizado.');
            $this->redirect('/usuarios/perfil');
        }
        // procesar cambio de contraseña
        else if (isset($_POST['cambiarContrasenia'])) {
            // verificar que las contraseñas no sean vacías
            if (empty($_POST['contrasenia']) || empty(trim($_POST['contrasenia1'])) || empty($_POST['contrasenia2'])) {
                SessionMessage::error(
                    'Debe especificar su contraseña actual y escribir dos veces su nueva contraseña.'
                );
                $this->redirect('/usuarios/perfil');
            }
            // verificar que la contraseña actual sea correcta
            if (!$this->Auth->User->checkPassword($_POST['contrasenia'])) {
                SessionMessage::error(
                    'La contraseña actual ingresada es incorrecta.'
                );
                $this->redirect('/usuarios/perfil');
            }
            // verificar que la contraseña nueva se haya escrito 2 veces de forma correcta
            if ($_POST['contrasenia1'] != $_POST['contrasenia2']) {
                SessionMessage::error(
                    'La contraseña nueva no coincide con la confirmación de contraseña.'
                );
                $this->redirect('/usuarios/perfil');
            }
            // actualizar contraseña
            if ($this->Auth->User->savePassword($_POST['contrasenia1'], $_POST['contrasenia'])) {
                $this->Auth->saveCache();
                SessionMessage::success(
                    'La contraseña del usuario ha sido actualizada.'
                );
            } else {
                SessionMessage::error('No fue posible cambiar la contraseña.');
            }
            $this->redirect('/usuarios/perfil');
        }
        // procesar creación de la autenticación secundaria
        else if (isset($_POST['crearAuth2'])) {
            unset($_POST['crearAuth2']);
            try {
                $this->Auth->User->createAuth2($_POST);
                $this->Auth->saveCache();
                SessionMessage::success(__(
                    'Desde ahora la cuenta está protegida con %s.',
                    $_POST['auth2']
                ));
            } catch (\Exception $e) {
                SessionMessage::error(__(
                    'No fue posible proteger la cuenta con %s: %s',
                    $_POST['auth2'],
                    $e->getMessage()
                ));
            }
            $this->redirect('/usuarios/perfil#auth');
        }
        // procesar destrucción de la autenticación secundaria
        else if (isset($_POST['destruirAuth2'])) {
            unset($_POST['destruirAuth2']);
            try {
                $this->Auth->User->destroyAuth2($_POST);
                $this->Auth->saveCache();
                SessionMessage::success(__(
                    'Su cuenta ya no está protegida con %s.',
                    $_POST['auth2']
                ));
            } catch (\Exception $e) {
                SessionMessage::error(__(
                    'No fue posible eliminar la protección con %s: %s',
                    $_POST['auth2'],
                    $e->getMessage()
                ));
            }
            $this->redirect('/usuarios/perfil#auth');
        }
        // mostrar formulario para edición
        else {
            $this->set([
                'changeUsername' => $this->changeUsername,
                'qrcode' => base64_encode($this->request->getFullUrlWithoutQuery() . ';' . $this->Auth->User->hash),
                'auths2' => \sowerphp\app\Model_Datasource_Auth2::getAll(),
                'layouts' => (array)config('app.ui.layouts'),
                'layout' => $this->Auth->User->config_app_ui_layout ?? $this->layout,
            ]);
        }
    }

    /**
     * Acción que permite registrar un nuevo usuario en la aplicación
     */
    public function registrar()
    {
        // si ya está logueado se redirecciona
        if ($this->Auth->logged()) {
            SessionMessage::info(sprintf(
                'Usuario <em>%s</em> tiene su sesión iniciada. Para registrar un nuevo usuaro primero debe cerrar esta sesión.',
                $this->Auth->User->usuario
            ));
            $this->redirect(
                $this->Auth->settings['redirect']['login']
            );
        }
        // si no se permite el registro se redirecciona
        $config = config('app.users.self_register');
        if (!$config['enabled']) {
            SessionMessage::error('El registro de usuarios está deshabilitado.');
            $this->redirect(
                $this->Auth->settings['redirect']['login']
            );
        }
        // colocar variable para terminos si está configurado
        if (!empty($config['terms'])) {
            $this->set('terms', $config['terms']);
        }
        $this->layout .= '.min';
        // si se envió formulario se procesa
        if (isset($_POST['usuario'])) {
            // verificar que campos no sean vacios
            if (empty($_POST['nombre']) || empty($_POST['usuario']) || empty($_POST['email'])) {
                SessionMessage::warning(
                    'Debe completar todos los campos del formulario.'
                );
                return;
            }
            // validar captcha
            try {
                \sowerphp\general\Utility_Google_Recaptcha::check();
            } catch (\Exception $e) {
                SessionMessage::error(__(
                    'Falló validación captcha: %s',
                    $e->getMessage()
                ));
                return;
            }
            // si existen términos y no se aceptaron se redirecciona
            if (!empty($config['terms']) && empty($_POST['terms_ok'])) {
                SessionMessage::warning(
                    'Debe aceptar los términos y condiciones de uso.'
                );
                return;
            }
            // validar que el usuario y/o correo no exista previamente
            $class = $this->Auth->settings['model'];
            $Usuario = new $class();
            $Usuario->nombre = $_POST['nombre'];
            $Usuario->usuario = \sowerphp\core\Utility_String::normalize($_POST['usuario']);
            $Usuario->email = mb_strtolower($_POST['email']);
            if ($Usuario->checkIfUserAlreadyExists()) {
                SessionMessage::warning(__(
                    'Nombre de usuario %s ya está en uso, elegir otro por favor.',
                    $Usuario->usuario
                ));
                return;
            }
            if ($Usuario->checkIfEmailAlreadyExists()) {
                SessionMessage::warning(__(
                    'Correo electrónico %s ya está en uso, elegir otro por favor.',
                    $Usuario->email
                ));
                return;
            }
            // asignar contraseña al usuario
            $contrasenia = \sowerphp\core\Utility_String::random(8);
            $Usuario->contrasenia = $Usuario->hashPassword($contrasenia);
            // asignar hash al usuario
            do {
                $Usuario->hash = \sowerphp\core\Utility_String::random(32);
            } while ($Usuario->checkIfHashAlreadyExists());
            if ($Usuario->save()) {
                // asignar grupos por defecto al usuario
                if (is_array($config) && !empty($config['groups'])) {
                    $Usuario->saveGroups($config['groups']);
                }
                // enviar correo
                $emailConfig = config('email.default');
                if (!empty($emailConfig['type']) && !empty($emailConfig['from'])) {
                    $layout = $this->layout;
                    $this->layout = null;
                    $this->set([
                        'nombre'=>$Usuario->nombre,
                        'usuario'=>$Usuario->usuario,
                        'contrasenia'=>$contrasenia,
                    ]);
                    $msg = $this->render('Usuarios/crear_email')->body();
                    $this->layout = $layout;
                    $email = new \sowerphp\core\Network_Email();
                    $email->to($Usuario->email);
                    $email->subject('Cuenta de usuario creada.');
                    $email->send($msg);
                    SessionMessage::success(__(
                        'Registro de usuario realizado, se envió su contraseña al correo %s',
                        $Usuario->email
                    ));
                } else {
                    SessionMessage::success(__(
                        'Registro de usuario realizado, su contraseña es <em>%s</em>',
                        $contrasenia
                    ));
                }
            } else {
                SessionMessage::error(
                    'El registro de su usuario falló por algún motivo desconocido.'
                );
            }
            $this->redirect('/usuarios/ingresar');
        }
    }

    /**
     * Acción que permite ingresar a la aplicación con un usuario ya
     * autenticado a través de un token provisto.
     * @param token Token de pre autenticación para validar la sesión.
     * @param usuario Usuario con el que se desea ingresar.
     * @param url URL a la cual redireccionar el usuario una vez ha iniciado sesión.
     */
    public function preauth($token = null, $usuario = null, $url = null)
    {
        // si se pasaron datos por POST tienen preferencia
        if (!empty($_POST['token'])) {
            $token = $_POST['token'];
            $usuario = !empty($_POST['usuario']) ? $_POST['usuario'] : null;
            $url = !empty($_POST['url']) ? $_POST['url'] : null;
        }
        // buscar clave de preauth, si no existe se indica que la
        // preautenticación no está disponible
        $enabled = config('app.users.preauth.enabled');
        if (!$enabled) {
            SessionMessage::error('La preautenticación no está disponible.');
            $this->redirect('/usuarios/ingresar');
        }
        if ($usuario) {
            $key = config('app.key');
            if (!$key) {
                SessionMessage::error('No hay clave global para preautenticación.');
                $this->redirect('/usuarios/ingresar');
            }
        }
        // definir url
        $url = $url ? base64_decode($url) : $this->Auth->settings['redirect']['login'];
        // si ya está logueado se redirecciona de forma silenciosa
        if ($this->Auth->logged()) {
            $this->redirect($url);
        }
        // procesar inicio de sesión con preauth, si no se puede autenticar se
        // genera un error
        $auth2_token = !empty($_GET['auth2_token']) ? $_GET['auth2_token'] : null;
        if (!$this->Auth->preauth($token, $usuario, $auth2_token)) {
            SessionMessage::error('La preautenticación del usuario falló.');
            $this->redirect('/usuarios/ingresar');
        }
        // todo ok -> redirigir
        $this->redirect($url);
    }

    /**
     * Acción que verifica el token ingresado y hace el pareo con telegram.
     */
    public function telegram_parear()
    {
        if (!empty($_POST['telegram_token'])) {
            $token = $_POST['telegram_token'];
            $telegram_user = $this->Cache->get('telegram.pairing.'.$token);
            // si no se encontró el usuario el token no es válido o expiró
            if (!$telegram_user) {
                SessionMessage::error('Token no válido o expiró, por favor, solicite uno nuevo al Bot con <strong><em>/token</em></strong>');
            }
            // se encontró el usuario, entonces guardar los datos del usuario de Telegram en el usuario de la aplicación web
            else {
                // verificar que no exista ya el usuario
                $Usuario = (new \sowerphp\app\Sistema\Usuarios\Model_Usuarios())->getUserByTelegramID(
                    $telegram_user['id'], $this->Auth->settings['model']
                );
                // cuenta de telegram ya está pareada
                if ($Usuario) {
                    SessionMessage::error(__(
                        'La cuenta de Telegram ya está pareada al usuario %s, primero debe cerrar la sesión de dicha cuenta.',
                        $Usuario->usuario
                    ));
                }
                // cuenta de telegram no está pareada, guardar
                else {
                    $this->Auth->User->set([
                        'config_telegram_id' => $telegram_user['id'],
                        'config_telegram_username' => $telegram_user['username'],
                    ]);
                    try {
                        $this->Auth->User->save();
                        $this->Auth->saveCache();
                        $this->Cache->delete('telegram.pairing.'.$token);
                        SessionMessage::success(__(
                            'Usuario @%s pareado con éxito.',
                            $telegram_user['username']
                        ));
                    } catch (\Exception $e) {
                        SessionMessage::error(
                            'Ocurrió un error al parear con Telegram: '.$e->getMessage()
                        );
                    }
                }
            }
        }
        $this->redirect('/usuarios/perfil#apps');
    }

    /**
     * Acción que desparea al usuario de Telegram.
     */
    public function telegram_desparear()
    {
        $this->Auth->User->set([
            'config_telegram_id' => null,
            'config_telegram_username' => null,
        ]);
        try {
            $this->Auth->User->save();
            $this->Auth->saveCache();
            SessionMessage::success('Su cuenta ya no está asociada a Telegram.');
        } catch (\Exception $e) {
            SessionMessage::error(
                'Ocurrió un error al eliminar su cuenta de Telegram: '.$e->getMessage()
            );
        }
        $this->redirect('/usuarios/perfil#apps');
    }

    /**
     * Método que permite cambiar el layout por defecto del usuario.
     */
    public function layout($layout = null)
    {
        // verificar se haya indicado un layout
        $layout = !empty($_POST['layout']) ? $_POST['layout'] : $layout;
        if (!$layout) {
            SessionMessage::error(
                'Debe indicar el nuevo diseño que desea utilizar en la aplicación.'
            );
            $this->redirect('/usuarios/perfil');
        }
        // cambiar layout
        $this->Auth->User->set([
            'config_app_ui_layout' => $layout,
        ]);
        $this->Auth->User->save();
        $this->Auth->saveCache();
        SessionMessage::success(
            'Se modificó el diseño por defecto de su cuenta.'
        );
        $this->redirect('/session/config/app.ui.layout/'.$layout.'/'.base64_encode('/usuarios/perfil'));
    }

    /**
     * Función de la API que permite obtener el perfil del usuario autenticado.
     */
    public function _api_perfil_GET()
    {
        $User = $this->Api->getAuthUser(false);
        if (is_string($User)) {
            $this->Api->send($User, 401);
        }
        extract($this->request->queries([
            'login' => false,
        ]));
        if ($login) {
            $User->updateLastLogin(
                $this->Auth->ip(true),
                $this->Auth->settings['multipleLogins']
            );
        }
        return [
            'id' => $User->id,
            'nombre' => $User->nombre,
            'usuario' => $User->usuario,
            'email' => $User->email,
            'hash' =>
                $User->getAuth2()
                ? (
                    (!empty($_GET['auth2_token']) && $User->checkAuth2($_GET['auth2_token']))
                    ? $User->hash
                    : null
                )
                : $User->hash,
            'ultimo_ingreso' => $User->lastLogin(),
            'grupos' => array_values($User->groups()),
        ];
    }

}
