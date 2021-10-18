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

// Menú para el módulo
Configure::write('nav.module', array(
    '/logs/online' => array(
        'name' => 'Visor en línea',
        'desc' => 'Visualización en línea de eventos registrados por el sistema',
        'icon' => 'fa fa-history',
    ),
    '/logs/buscar' => array(
        'name' => 'Buscar por usuario',
        'desc' => 'Visualización de eventos registrados por el sistema de un usuario específico',
        'icon' => 'fa fa-search',
    ),
));
