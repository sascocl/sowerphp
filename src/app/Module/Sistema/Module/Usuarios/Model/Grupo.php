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

use \sowerphp\autoload\Model;

/**
 * Modelo singular de la tabla "grupo" de la base de datos.
 *
 * Permite interactuar con un registro de la tabla.
 */
class Model_Grupo extends Model
{

    /**
     * Metadatos del modelo.
     *
     * @var array
     */
    protected $meta = [
        'model' => [
            'verbose_name' => 'Grupo',
            'verbose_name_plural' => 'Grupos',
            'db_table_comment' => 'Grupos a los que puede pertenecer un usuario.',
            'ordering' => ['id'],
        ],
        'fields' => [
            'id' => [
                'type' => self::TYPE_INCREMENTS,
                'verbose_name' => 'ID',
                'help_text' => 'Identificador (serial).',
            ],
            'grupo' => [
                'type' => self::TYPE_STRING,
                'null' => true,
                'blank' => true,
                'max_length' => 30,
                'verbose_name' => 'Grupo',
                'help_text' => 'Nombre del grupo.',
            ],
            'activo' => [
                'type' => self::TYPE_BOOLEAN,
                'default' => true,
                'blank' => true,
                'verbose_name' => 'Activo',
                'help_text' => 'Indica si el grupo se encuentra activo.',
            ],
        ],
    ];

}
