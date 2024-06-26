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

namespace sowerphp\core;

/**
 * Clase base para todo comando de la Shell
 */
abstract class Shell
{

    public $stdout; ///< Atributo con el objeto para la salida de datos
    public $verbose = 0; ///< Nivel de "verbose" (cuanto "dice" el comando)
    protected $allow_multiple_instances = false; ///< Indica si el proceso permite o no múltiples instancias idénticas al mismo tiempo

    /**
     * Constructor de la clase, asigna salida estándar a stdout
     */
    public function __construct()
    {
        $this->stdout = new Shell_Output('php://stdout');
    }

    /**
     * Método que imprime a la salida indicada
     * @param message Mensaje que se desea imprimir
     * @param newlines Cuantas nuevas líneas se deben agregar
     * @return Caracteres escritos (o falso si falló)
     */
    public function out($message = null, $newlines = 1)
    {
        return $this->stdout->write($message, $newlines);
    }

    /**
     * Método que lee desde la entrada estándar
     * @param message Mensaje que se desea imprimir antes de leer
     * @param newlines Cuantas nuevas líneas se deben agregar después del mensaje
     * @param trim Si se debe usar trim en lo leído (por defecto true)
     * @return Lo leído desde el teclado
     */
    public function in($message = null, $newlines = 0, $trim = true)
    {
        $this->out($message, $newlines);
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        fclose($handle);
        return $trim ? trim($line) : $line;
    }

    /**
     * Guardar un archivo con cierto contenido
     * @param filename Nombre del archivo
     * @param data Datos que se deben guardar en el archivo
     */
    public function createFile($filename, $data)
    {
        file_put_contents($filename, $data);
    }

    /**
     * Método para mostrar estadísticas finales de la ejecución del comando
     * @param stream Se permite elegir a través de que stream se enviarán las estadísticas
     */
    public function showStats($stream = 'php://stderr')
    {
        $out = new Shell_Output($stream);
        // tiempo que tomó la ejecución del comando
        $time = microtime(true) - TIME_START;
        if ($time < 60) {
            $out->write('Proceso ejecutado en '.num($time,1).' segundos.'."\n");
        } else if ($time < 3600) {
            $out->write('Proceso ejecutado en '.num($time/60,1).' minutos.'."\n");
        } else {
            $out->write('Proceso ejecutado en '.num($time/3600,1).' horas.'."\n");
        }
    }

    /**
     * Método que indica si este comando ("shell script") puede ejecutar múltiples instancias
     */
    public function canHaveMultipleInstances()
    {
        return $this->allow_multiple_instances;
    }

}
