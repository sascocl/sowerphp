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

namespace sowerphp\general;

/**
 * Controlador para página de contacto
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2018-10-15
 */
class Controller_Contacto extends \Controller_App
{

    /**
     * Método para autorizar la carga de index en caso que hay autenticación
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2015-03-18
     */
    public function beforeFilter()
    {
        if (isset($this->Auth)) {
            $this->Auth->allow('index');
        }
        parent::beforeFilter();
    }

    /**
     * Método que desplegará y procesará el formulario de contacto
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2020-06-20
     */
    public function index()
    {
        // si no hay datos para el envió del correo electrónico no
        // permirir cargar página de contacto
        if (\sowerphp\core\Configure::read('email.default')===NULL) {
            \sowerphp\core\Model_Datasource_Session::message(
                __('La página de contacto no se encuentra disponible'), 'error'
            );
            $this->redirect('/');
        }
        // colocar variable para captcha (si está configurado)
        $captcha_public_key = \sowerphp\core\Configure::read('recaptcha.public_key');
        if ($captcha_public_key) {
            $this->set([
                'captcha_public_key' => $captcha_public_key,
                'language' => \sowerphp\core\Configure::read('language'),
            ]);
        }
        // si se envió el formulario se procesa
        if (isset($_POST['submit'])) {
            // si existe la configuración para recaptcha se debe validar
            $captcha_private_key = \sowerphp\core\Configure::read('recaptcha.private_key');
            if ($captcha_private_key) {
                if (empty($_POST['g-recaptcha-response'])) {
                    \sowerphp\core\Model_Datasource_Session::message(
                        __('Debe completar el captcha para enviar su mensaje'), 'warning'
                    );
                    $this->response->status(412);
                    return;
                }
                $recaptcha = new \ReCaptcha\ReCaptcha($captcha_private_key);
                $resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
                if (!$resp->isSuccess()) {
                    \sowerphp\core\Model_Datasource_Session::message(
                        __('El captcha enviado es incorrecto'), 'error'
                    );
                    $this->response->status(412);
                    return;
                }
            }
            // enviar email
            $_POST['nombre'] = trim(strip_tags($_POST['nombre']));
            $_POST['correo'] = trim(strip_tags($_POST['correo']));
            $_POST['mensaje'] = trim(strip_tags($_POST['mensaje']));
            if (!empty($_POST['nombre']) and !empty($_POST['correo']) and !empty($_POST['mensaje'])) {
                $email = new \sowerphp\core\Network_Email();
                $email->replyTo($_POST['correo'], $_POST['nombre']);
                $email->to(\sowerphp\core\Configure::read('email.default.to'));
                $email->subject(!empty($_POST['asunto']) ? trim(strip_tags($_POST['asunto'])) : __('Contacto desde %s #%d', $this->request->url, date('YmdHis')));
                $msg = $_POST['mensaje']."\n\n".'-- '."\n".$_POST['nombre']."\n".$_POST['correo'];
                $status = $email->send($msg);
                if ($status===true) {
                    \sowerphp\core\Model_Datasource_Session::message(
                        __('Su mensaje ha sido enviado, se responderá a la brevedad.'), 'ok'
                    );
                    $this->redirect('/contacto');
                } else {
                    \sowerphp\core\Model_Datasource_Session::message(
                        __('Ha ocurrido un error al enviar su mensaje, por favor intente nuevamente.<br /><em>%s</em>', $status['message']), 'error'
                    );
                }
            } else {
                \sowerphp\core\Model_Datasource_Session::message(
                    __('Por favor, completar todos los campos del formulario'), 'error'
                );
            }
        }
    }

}
