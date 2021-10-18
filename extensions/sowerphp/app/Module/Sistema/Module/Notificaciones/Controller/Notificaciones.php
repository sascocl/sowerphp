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

// namespace del controlador
namespace sowerphp\app\Sistema\Notificaciones;

/**
 * Clase para el controlador asociado a la tabla notificacion de la base de
 * datos
 * Comentario de la tabla:
 * Esta clase permite controlar las acciones entre el modelo y vista para la
 * tabla notificacion
 * @author SowerPHP Code Generator
 * @version 2015-05-05 12:03:07
 */
class Controller_Notificaciones extends \Controller_Maintainer
{

    protected $namespace = __NAMESPACE__; ///< Namespace del controlador y modelos asociados
    protected $columnsView = [
        'listar'=>['id', 'fechahora', 'de', 'para', 'descripcion', 'leida']
    ]; ///< Columnas que se deben mostrar en las vistas

    public function beforeFilter()
    {
        $this->Auth->allowWithLogin('index', 'abrir');
        parent::beforeFilter();
    }

    public function index()
    {
        $this->set([
            'notificaciones' => (new Model_Notificaciones())->getByUser($this->Auth->User->id),
        ]);
    }

    public function abrir($notificacion)
    {
        // verificar que la notificación exista
        $Notificacion = new Model_Notificacion($notificacion);
        if (!$Notificacion->exists()) {
            \sowerphp\core\Model_Datasource_Session::message('Notificación solicitada no existe', 'error');
            $this->redirect('/sistema/notificaciones/notificaciones');
        }
        // si el usuario autenticado no es dueño de la notificación entonces error
        if ($this->Auth->User->id != $Notificacion->para) {
            \sowerphp\core\Model_Datasource_Session::message('Usuario autenticado no es destinatario de la notificación solicitada', 'error');
            $this->redirect('/sistema/notificaciones/notificaciones');
        }
        // marcar como leída
        $Notificacion->leida();
        $this->redirect($Notificacion->enlace);
    }

    public function enviar()
    {
        $this->set([
            'metodos' => [
                'db' => 'Guardar notificación en base de datos',
                'email' => 'Enviar notificación por correo electrónico',
            ],
        ]);
    }

    public function _api_leida_GET($notificacion)
    {
        // obtener usuario autenticado
        if (($User=$this->Auth->User)===false and is_string($User=$this->Api->getAuthUser())) {
            $this->Api->send('Usuario no autenticado', 401);
        }
        // verificar que la notificación exista
        $Notificacion = new Model_Notificacion($notificacion);
        if (!$Notificacion->exists()) {
            $this->Api->send('Notificación solicitada no existe', 404);
        }
        // si el usuario autenticado no es dueño de la notificación entonces error
        if ($User->id != $Notificacion->para) {
            $this->Api->send('Usuario autenticado no es destinatario de la notificación solicitada', 403);
        }
        // marcar como leída
        $Notificacion->leida();
        return true;
    }

}
