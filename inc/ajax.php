<?php
namespace Inc;

class Ajax {
    public $method;
    public $url;
    public $args;

    public static $serverURI = "http://127.0.0.1:8888";

    function __construct( $method, $url, $args )
    {
        $this->method = $method;
        $this->url = self::$serverURI . $url;
        $this->args = $args ? $args : array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nOrigin:localhost:12555\r\n",
                'method'  => strtoupper( $method ),
            )
        );
    }

    public function send( $data ) {
        $this->args['http']['content'] = http_build_query( [ 'ws_php_body' => $data ] );
        $context = stream_context_create($this->args);

        $result = file_get_contents($this->url, false, $context);

        if ($result === FALSE) {
            echo "$result";
            return self::error_handler( $result );
        } else {
            try {
                $result = json_decode($result);
                if ($result->error) {
                    echo "\nReceived an error from nodejs: " . $result->message;
                }
            } catch (\Exception $exception) {
                echo $exception;
            }
        }


        return $result;
    }

    public static function error_handler( $error ) {
        $error_string = $error;// $error->get_error_message();

        return [ 'error' => '<div id="message" class="error"><p>' . $error_string . '</p></div>' ];
    }
}

?>