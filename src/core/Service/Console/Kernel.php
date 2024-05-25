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

class Service_Console_Kernel implements Interface_Service
{

    public function register()
    {
    }

    public function boot()
    {
    }

    public function handle()
    {
        global $argv;
        return Shell_Exec::run($argv);
    }

    private function handleException(\Exception $exception): void
    {
        /*
        $stdout = new Shell_Output('php://stdout');
        $stdout->write("\n".'<error>'.$data['exception'].':</error>', 2);
        $stdout->write("\t".'<error>'.str_replace("\n", "\n\t", $data['message']).'</error>', 2);
        $stdout->write("\t".'<error>'.str_replace("\n", "\n\t", $data['trace']).'</error>', 2);
        exit($data['code']);
        */
    }

}