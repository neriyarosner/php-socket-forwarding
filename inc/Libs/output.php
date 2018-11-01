<?php
namespace Inc\Libs;

class Output {
    static $verbosity = 3;

    static function error( $message, ...$args ) {
        if ( self::$verbosity >= 0 ) {
            $caller = self::getCaller();

            echo "\n[!] ($caller) $message" . self::parseArgs( $args );
        }
    }
    static function warning( $message, ...$args ) {
        if ( self::$verbosity >= 1 ) {
            $caller = self::getCaller();

            echo "\n[-] ($caller) $message" . self::parseArgs( $args );
        }
    }
    static function info( $message, ...$args ) {
        if ( self::$verbosity >= 2 ) {
            echo "\n[+] $message" . self::parseArgs( $args );
        }
    }
    static function debug( $message, ...$args ) {
        if ( self::$verbosity >= 3 ) {
            echo "\n[*] $message" . self::parseArgs( $args );
        }
    }
    static function extra_debug( $message, ...$args ) {
        if ( self::$verbosity >= 4 ) {
            echo "\n[***] $message" . self::parseArgs( $args );
        }
    }

    static function getCaller() {
        $caller = debug_backtrace()[2]['function'];

        if ( $caller === "__construct" ) {
            $class = explode('\\', debug_backtrace()[2]['class']);
            $caller = $class[ count( $class ) - 1 ] . " constructor";
        }

        return $caller;
    }

    static function parseArgs( $args ) {
        $message = "";
        $prefix = PHP_EOL . "\t";
        $postfix = PHP_EOL;

        if ( $args ) {
            $message =  "";

            foreach ($args as $index => $arg) {
                $newArg = $arg ;

                if ( gettype( $arg ) === 'object' )
                    $newArg = json_encode( $arg );
                else if ( gettype( $arg ) === 'array' )
                    $newArg = implode( " ", $arg );

                $message = $message . $prefix . $newArg . ($index < count($args) - 1 ? ", " : '');
            }
        }

        $message = $message . PHP_EOL . "-------------------------------";

        return $message;
    }
}