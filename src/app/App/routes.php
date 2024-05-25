<?php

/**
 * SowerPHP: Framework PHP hecho en Chile.
 * Copyright (C) SowerPHP <https://www.sowerphp.org>
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

// rutas para servicios web
\sowerphp\core\Routing_Router::connect('/api/:controller/*', [
    'action' => 'api',
]);
\sowerphp\core\Routing_Router::connect('/api/sistema/logs/:controller/*', [
    'module' => 'Sistema.Logs',
    'action' => 'api',
]);
\sowerphp\core\Routing_Router::connect('/api/sistema/notificaciones/:controller/*', [
    'module' => 'Sistema.Notificaciones',
    'action' => 'api',
]);
\sowerphp\core\Routing_Router::connect('/api/sistema/usuarios/:controller/*', [
    'module' => 'Sistema.Usuarios',
    'action' => 'api',
]);
if (app('module')->isModuleLoaded('Dev')) {
    \sowerphp\core\Routing_Router::connect('/api/dev/:controller/*', [
        'module' => 'Dev',
        'action' => 'api',
    ]);
}