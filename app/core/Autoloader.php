<?php

namespace App\Core;

/**
 * Class Autoloader
 * @package Lacouisine
 */
class Autoloader{

    /**
     * record the autoloader
     * @return void
     */
    static function register() : void {

        spl_autoload_register(array(__CLASS__, 'autoload'));

    }

    /**
     * include the file corresponding to the class
     * @param string $class class name to load
     * @return void
     */
    static function autoload($class) : void {

        $class = str_replace(__NAMESPACE__ . '\\', '', $class);

        $class = str_replace('\\', '/', $class);

        require $class . '.php';
        
    }

}