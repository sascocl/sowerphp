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

namespace sowerphp\app;

/**
 * Clase que implementa los métodos básicos de un mantenedor, métodos CRUD.
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2018-10-17
 */
class Controller_Maintainer extends \Controller_App
{

    protected $model = false; ///< Atributo con el namespace y clase del modelo singular
    protected $models = false; ///< Atributo con el namespace y clase del modelo plural
    protected $module_url; ///< Atributo con la url para acceder el módulo
    protected $deleteRecord = true; ///< Indica si se permite o no borrar registros
    protected $contraseniaNames = ['contrasenia', 'clave', 'password', 'pass']; ///< Posibles nombres de campo tipo contraseña
    protected $actionsColsWidth = 110; ///< Ancho de la columna de acciónes en acción listar
    protected $extraActions = []; ///< iconos extra para la columna de acciones

    /**
     * Constructor del controlador
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2014-04-22
     */
    public function __construct (\sowerphp\core\Network_Request $request, \sowerphp\core\Network_Response $response)
    {
        parent::__construct ($request, $response);
        $this->setModelName();
        $this->module_url = $this->setModuleUrl ($this->request->params['module']);
    }

    /**
     * Método que asigna los namespaces y nombres de los modelos tanto singular
     * como plural usados por este controlador
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2014-12-17
     */
    private function setModelName ()
    {
        if (!$this->models)
            $this->models = \sowerphp\core\Utility_Inflector::camelize($this->request->params['controller']);
        if (!$this->model)
            $this->model = \sowerphp\core\Utility_Inflector::singularize($this->models);
        $this->set('models', $this->models);
        $this->set('model', $this->model);
        $this->model = '\\'.$this->namespace.'\Model_'.$this->model;
        $this->models = '\\'.$this->namespace.'\Model_'.$this->models;
    }

    /**
     * Método que asigna la url del módulo que se usa en el controlador
     * @param modulo Nombre del módulo donde se generarán los archivos
     * @return URL que se usa para acceder al módulo
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2017-03-15
     */
    private function setModuleUrl ($modulo = '')
    {
        $partes = explode('.', $modulo);
        $module_url = '';
        foreach ($partes as &$p) {
            $module_url .= \sowerphp\core\Utility_Inflector::underscore($p).'/';
        }
        return $module_url!='/' ? ('/'.$module_url) : $module_url;
    }

    /**
     * Método que busca la vista que se deberá renderizar
     * @param view Vista que se desea renderizar
     * @param location No se utiliza, esta por compatibilidad con método padre
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2016-02-09
     */
    protected function renderView($view = null, $location = null)
    {
        $this->autoRender = false;
        $ControllerName = str_replace($this->namespace.'\Controller_', '', get_class($this));
        if (\sowerphp\core\View::location($ControllerName.'/'.$view, $this->request->params['module'])) {
            return parent::render($ControllerName.'/'.$view, $location);
        } else {
            return parent::render('Maintainer/'.$view, 'sowerphp/app');
        }
    }

    /**
     * Método que permite forzar las opciones de búsqueda para la acción listar
     * esto permite a cierto usuario mostrar sólo cierto listado de registros
     * y no todos, esto evita tener que reprogramar la acción listar :-)
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2016-02-09
     */
    protected function forceSearch(array $data)
    {
        // se asignan datos forzados para búsqueda
        $search = [];
        foreach ($data as $var => $val) {
            $search[] = $var.':'.$val;
        }
        // se copian filtros extras, menos los forzados
        if (!empty($_GET['search'])) {
            $vars = array_keys($data);
            $filters = explode(',', $_GET['search']);
            foreach ($filters as &$filter) {
                list($var, $val) = explode(':', $filter);
                if (!in_array($var, $vars))
                    $search[] = $var.':'.$val;
            }
        }
        // se vuelve a armar la búsqueda
        $_GET['search'] = implode(',', $search);
    }

    /**
     * Acción para listar los registros de la tabla
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2020-08-20
     */
    public function listar($page = 1, $orderby = null, $order = 'A')
    {
        $model = $this->model;
        // crear objeto
        $Objs = new $this->models();
        // si se debe buscar se agrega filtro
        $searchUrl = null;
        $search = array();
        if (!empty($_GET['search'])) {
            $searchUrl = '?search='.$_GET['search'];
            $filters = explode(',', $_GET['search']);
            $where = [];
            $vars = [];
            foreach ($filters as &$filter) {
                list($var, $val) = explode(':', $filter);
                $search[$var] = $val;
                // si el valor es '!null' se compara contra IS NOT NULL
                if ($val == '!null') {
                    $where[] = $var.' IS NOT NULL';
                }
                // si el valor es null o 'null' se compara contra IS NULL
                else if ($val === null or $val == 'null') {
                    $where[] = $var.' IS NULL';
                }
                // si es una FK se filtra con igualdad
                else if (!empty($model::$columnsInfo[$var]['fk'])) {
                    $where[] = $var.' = :'.$var;
                    $vars[':'.$var] = $val;
                }
                // si es un campo de texto se filtrará con LIKE
                else if (in_array($model::$columnsInfo[$var]['type'], ['char', 'character varying', 'varchar', 'text'])) {
                    $where[] = 'LOWER('.$var.') LIKE :'.$var;
                    $vars[':'.$var] = '%'.strtolower($val).'%';
                }
                // si es un tipo fecha con hora se usará like
                else if (in_array($model::$columnsInfo[$var]['type'], ['timestamp', 'timestamp without time zone'])) {
                    $where[] = 'CAST('.$var.' AS TEXT) LIKE :'.$var;
                    $vars[':'.$var] = $val.' %';
                }
                // si es cualquier otro caso se comparará con una igualdad
                else {
                    $where[] = $var.' = :'.$var;
                    $vars[':'.$var] = $val;
                }
            }
            $Objs->setWhereStatement($where, $vars);
        }
        // si se debe ordenar se agrega
        if (isset($model::$columnsInfo[$orderby])) {
            $Objs->setOrderByStatement([$orderby=>($order=='D'?'DESC':'ASC')]);
        }
        // total de registros
        $registers_total = $Objs->count();
        // paginar si es necesario
        if ((integer)$page>0) {
            $registers_per_page = \sowerphp\core\Configure::read('app.registers_per_page');
            $pages = ceil($registers_total/$registers_per_page);
            $Objs->setLimitStatement($registers_per_page, ($page-1)*$registers_per_page);
            if ($page != 1 && $page > $pages) {
                $this->redirect(
                    $this->module_url.$this->request->params['controller'].'/listar/1'.($orderby ? '/'.$orderby.'/'.$order : '').$searchUrl
                );
            }
        }
        // crear variable con las columnas para la vista
        if (!empty($this->columnsView['listar'])) {
            $columns = [];
            foreach ($model::$columnsInfo as $col => &$info) {
                if (in_array($col, $this->columnsView['listar'])) {
                    $columns[$col] = $info;
                }
            }
        } else {
            $columns = $model::$columnsInfo;
        }
        // setear variables
        $this->set(array(
            'module_url' => $this->module_url,
            'controller' => $this->request->params['controller'],
            'page' => $page,
            'orderby' => $orderby,
            'order' => $order,
            'searchUrl' => $searchUrl,
            'search' => $search,
            'Objs' => $Objs->getObjects($this->model),
            'columns' => $columns,
            'registers_total' => $registers_total,
            'pages' => isset($pages) ? $pages : 0,
            'linkEnd' => ($orderby ? '/'.$orderby.'/'.$order : '').$searchUrl,
            'fkNamespace' => $model::$fkNamespace,
            'comment' => $model::$tableComment,
            'listarFilterUrl' => '?listar='.base64_encode('/'.$page.($orderby ? '/'.$orderby.'/'.$order : '').$searchUrl),
            'deleteRecord' => $this->deleteRecord,
            'actionsColsWidth' => $this->actionsColsWidth,
            'extraActions' => $this->extraActions,
        ));
        // renderizar
        $this->renderView('listar');
    }

    /**
     * Acción para crear un registro en la tabla
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2015-04-24
     */
    public function crear ()
    {
        $filterListar = !empty($_GET['listar']) ? base64_decode($_GET['listar']) : '';
        // si se envió el formulario se procesa
        if (isset($_POST['submit'])) {
            $Obj = new $this->model();
            $Obj->set($_POST);
            if (!$Obj->exists()) {
                foreach($_FILES as $name => &$file) {
                    if (!$file['error']) {
                        $Obj->setFile($name, $file);
                    }
                }
                $msg = $Obj->save() ? 'Registro creado' : 'Registro no creado';
                \sowerphp\core\Model_Datasource_Session::message($msg, 'ok');
                $this->redirect(
                    $this->module_url.$this->request->params['controller'].'/listar'.$filterListar
                );
            } else {
                \sowerphp\core\Model_Datasource_Session::message('Registro ya existe', 'error');
            }
        }
        // setear variables
        $model = $this->model;
        $this->set(array(
            'columnsInfo' => $model::$columnsInfo,
            'fkNamespace' => $model::$fkNamespace,
            'accion' => 'Crear',
            'columns' => $model::$columnsInfo,
            'contraseniaNames' => $this->contraseniaNames,
            'listarUrl' => $this->module_url.$this->request->params['controller'].'/listar'.$filterListar,
        ));
        // renderizar
        $this->renderView('crear_editar');
    }

    /**
     * Acción para editar un registro de la tabla
     * @param pk Parámetro que representa la PK, pueden ser varios parámetros los pasados
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2020-03-04
     */
    public function editar ($pk)
    {
        $filterListar = !empty($_GET['listar']) ? base64_decode($_GET['listar']) : '';
        $Obj = new $this->model(array_map('urldecode', func_get_args()));
        // si el registro que se quiere editar no existe error
        if(!$Obj->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'Registro ('.implode(', ', func_get_args()).') no existe, no se puede editar',
                'error'
            );
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'.$filterListar
            );
        }
        // si no se ha enviado el formulario se mostrará
        $model = $this->model;
        if(!isset($_POST['submit'])) {
            $this->set(array(
                'Obj' => $Obj,
                'columns' => $model::$columnsInfo,
                'contraseniaNames' => $this->contraseniaNames,
                'fkNamespace' => $model::$fkNamespace,
                'accion' => 'Editar',
                'listarUrl' => $this->module_url.$this->request->params['controller'].'/listar'.$filterListar,
            ));
            // renderizar
            $this->renderView('crear_editar');
        }
        // si se envió el formulario se procesa
        else {
            foreach ($model::$columnsInfo as $col => &$info) {
                if (in_array($col, $this->contraseniaNames) and empty($_POST[$col])) {
                    $_POST[$col] = $Obj->$col;
                }
            }
            $Obj->set($_POST);
            foreach($_FILES as $name => &$file) {
                if (!$file['error']) {
                    $Obj->setFile($name, $file);
                }
            }
            $msg = $Obj->save() ? 'Registro ('.implode(', ', func_get_args()).') editado' : 'Registro ('.implode(', ', func_get_args()).') no editado';
            \sowerphp\core\Model_Datasource_Session::message($msg, 'ok');
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'.$filterListar
            );
        }
    }

    /**
     * Acción para eliminar un registro de la tabla
     * @param pk Parámetro que representa la PK, pueden ser varios parámetros los pasados
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2020-03-04
     */
    public function eliminar ($pk)
    {
        if (!$this->deleteRecord) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No se permite el borrado de registros', 'error'
            );
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'.$filterListar
            );
        }
        $filterListar = !empty($_GET['listar']) ? base64_decode($_GET['listar']) : '';
        $Obj = new $this->model(array_map('urldecode', func_get_args()));
        // si el registro que se quiere eliminar no existe error
        if(!$Obj->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'Registro ('.implode(', ', func_get_args()).') no existe, no se puede eliminar',
                'error'
            );
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'.$filterListar
            );
        }
        try {
            $Obj->delete();
            \sowerphp\core\Model_Datasource_Session::message(
                'Registro ('.implode(', ', func_get_args()).') eliminado', 'ok'
            );
        } catch (\sowerphp\core\Exception_Model_Datasource_Database $e) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No se pudo eliminar el registro ('.implode(', ', func_get_args()).'): '.$e->getMessage(), 'error'
            );
        }
        $this->redirect(
            $this->module_url.$this->request->params['controller'].'/listar'.$filterListar
        );
    }

    /**
     * Método para descargar un archivo desde la base de datos
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
     * @version 2014-11-14
     */
    public function d ($campo, $pk)
    {
        // si el campo que se solicita no existe error
        $model = $this->model;
        if (!isset($model::$columnsInfo[$campo.'_data'])) {
            \sowerphp\core\Model_Datasource_Session::message(
                'Campo '.$campo.' no existe', 'error'
            );
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'
            );
        }
        $pks = array_slice(func_get_args(),1);
        $Obj = new $this->model($pks);
        // si el registro que se quiere eliminar no existe error
        if(!$Obj->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'Registro ('.implode(', ', $pks).') no existe. No se puede obtener '.$campo,
                'error'
            );
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'
            );
        }
        if ($Obj->{$campo.'_size'}==0) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No hay datos para el campo '.$campo.' en el registro ('.implode(', ', $pks).')',
                'error'
            );
            $this->redirect(
                $this->module_url.$this->request->params['controller'].'/listar'
            );
        }
        // entregar archivo
        $this->response->sendFile([
            'name' => $Obj->{$campo.'_name'},
            'type' => $Obj->{$campo.'_type'},
            'size' => $Obj->{$campo.'_size'},
            'data' => $Obj->{$campo.'_data'},
        ]);
    }

}
