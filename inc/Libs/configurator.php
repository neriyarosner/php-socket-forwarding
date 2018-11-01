<?php
namespace Inc\Libs;

class Configurator {
    private static $_configuration = array();

    public static function write($key, $value) {
        self::$_configuration[$key] = $value;
    }

    public static function read($key) {
        return self::$_configuration[$key];
    }
}