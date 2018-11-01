<?php
namespace Inc\Libs;

class Ajax {
    public $method;
    public $url;
    public $args;

    public static $serverURI = "https://app.cargo-express.co.il";

    function __construct( $method, $url, $args = false)
    {
        $this->method = $method;
        $this->url = self::$serverURI . $url;
        $this->args = $args ? $args : array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nOrigin:localhost:7347\r\n",
                'method'  => strtoupper( $method ),
            )
        );
    }

    public function send( $data = null ) {
        $this->args['http']['content'] = http_build_query( [ 'ws_php_body' => $data ] );
        $context = stream_context_create($this->args);

        $result = file_get_contents($this->url, false, $context);

        if ($result === FALSE) {
            echo "\n error false result: $result";
            return self::error_handler( $result );
        } else {
            try {
                $result = json_decode( $result );
                if ( $result && property_exists( $result, 'error' ) && $result->error ) {
                    // Output::error("Received an error from nodejs: $result->message for request: $this->url");
                }
            } catch ( \Exception $exception ) {
                echo "\nexception: $exception, result: $result";
            }
        }

        return $result;
    }

    public static function error_handler( $error ) {
        $error_string = $error;// $error->get_error_message();

        return (object)[ 'error' => true, 'message' => $error_string, 'html' => '<div id="message" class="error"><p>' . $error_string . '</p></div>' ];
    }
}

?>