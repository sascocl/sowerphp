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
 * Clase para crear una galería de imágenes
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-11-23
 */
class View_Helper_Gallery
{

    private $_base;
    private $_name;

    public function __construct ($dir = null, $name = 'galeria')
    {
        // obtener base
        $this->_base = _BASE;
        $this->_name = $name;
        // si se indico un directorio se genera e imprime (like TableHelper)
        if ($dir) {
            echo $this->generate($dir);
        }
    }

    public function generate ($dir)
    {
        // buffer para ir dibujando la galería
        $buffer = '';
        // obtener cabecera
        $buffer .= $this->header();
        // inicio de la galería
        $buffer .= '<div>'."\n";
        // obtener imagenes (si existen miniaturas se usan)
        if(file_exists(DIR_WEBSITE.'/webroot'.$dir.'/miniaturas')) {
            $imagenes = scandir(DIR_WEBSITE.'/webroot'.$dir.'/miniaturas');
            $miniaturas = '/miniaturas';
        } else {
            $imagenes = scandir(DIR_WEBSITE.'/webroot'.$dir);
            $miniaturas = '';
        }
        // mostrar imagenes
        foreach($imagenes as &$imagen) {
            if (!is_dir(DIR_WEBSITE.'/webroot'.$dir.$miniaturas.'/'.$imagen)) {
                $buffer .= '<a href="'.$this->_base.$dir.'/'.$imagen.'" rel="prettyPhoto['.$this->_name.']"><img src="'.$this->_base.$dir.$miniaturas.'/'.$imagen.'" alt="'.$imagen.'" class="pp-thumbnail" /></a>'."\n";
            }
        }
        // fin de la galería
        $buffer .= '</div>'."\n";
        // retornar bufer
        return $buffer;
    }

    private function header ()
    {
        return '
            <link rel="stylesheet" href="'.$this->_base.'/css/prettyPhoto.css" type="text/css" media="screen" charset="utf-8" />
            <script src="'.$this->_base.'/js/jquery.browser.min.js" type="text/javascript" charset="utf-8"></script>
            <script src="'.$this->_base.'/js/jquery.prettyPhoto.js" type="text/javascript" charset="utf-8"></script>
            <style type="text/css">
            img.pp-thumbnail {
                margin: 5px;
                padding: 3px;
                border: solid 1px #CCC;
                -moz-box-shadow: 1px 1px 5px #999;
                -webkit-box-shadow: 1px 1px 5px #999;
                box-shadow: 1px 1px 5px #999;
                max-width: 160px;
                max-height: 120px;
            }
            </style>
            <script type="text/javascript" charset="utf-8">
                $(document).ready(function(){
                    $("a[rel^=\'prettyPhoto\']").prettyPhoto();
                });
            </script>
        ';
    }

}
