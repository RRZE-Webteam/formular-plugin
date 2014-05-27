<?php

namespace pattern;

abstract class Singleton {

    final private function __construct() {

        if(isset(static::$instance))

            throw new Exception("Eine Instanz der Klasse ".get_called_class()." gibt es bereits.");

        static::init();
    }

    final private function __clone() {
        throw new Exception("Eine Instanz der Klasse ".get_called_class()." kann nicht geklont werden.");
    }

	final private function __wakeup() {
        throw new Exception("Eine Instanz der Klasse ".get_called_class()." kann nicht wiederhergestellt werden.");
    }

    final public static function get_instance() {
        return isset(static::$instance) ? static::$instance : static::$instance = new static;
    }

    protected function init(){}

}