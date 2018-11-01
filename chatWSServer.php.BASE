<?php
require __DIR__ . '/vendor/autoload.php';

use Inc\Connection;
use Inc\Ajax;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface as Conn;

use Ratchet\RFC6455\Messaging\MessageInterface as MsgInterface;

use Ratchet\Server\IoServer;
use React\Socket\Server as Reactor;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

use React\EventLoop\Factory as ReactFactory;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;

class Configurator {
    private static $_configuration = array();

    public static function write($key, $value) {
        self::$_configuration[$key] = $value;
    }

    public static function read($key) {
        return self::$_configuration[$key];
    }
}

class Chat implements MessageComponentInterface {
    protected $node_socket;
    protected $connections;
    private $REPRESENTATIVE_INTERVAL_SECONDS = 5;

    private $loop;

    public function __construct( $loop )
    {
        $this->loop = $loop;
        $this->connections = Configurator::read('connections');
    }

    public function onOpen( Conn $conn )
    {
        $connection = new Connection( $conn->resourceId, $conn);
        echo "\nsending init to client: " . $conn->resourceId;
        $connection->sendClientMessage( [ 'init' => true, 'connectionID' => $conn->resourceId ] );

        $this->connections->attach( $connection );
    }

    public function onMessage( Conn $from, $data )
    {
        // $msg = json_decode( $data );
        $numRecv = count( $this->connections ) - 1;
        $connection = Connection::findConnectionById( $this->connections, $from->resourceId );

        if ( $connection && $data )
        {
            $parsedData = json_decode( $data );

            if ( property_exists( $parsedData, 'getRepresentative' ) && $parsedData->getRepresentative ) {
                $this->getRep( $from->resourceId, $parsedData->supportID );
            }

            if ( property_exists( $parsedData, 'init' ) && $parsedData->init ) {
                echo "\nrecieved init from client and passing to server " . $from->resourceId;

                $connection->setSupportID( $parsedData->supportID );

                $connection->sendServerInit( $from->resourceId, $parsedData );
            } else {
                $connection->sendServerMessage( $parsedData->message );
            }
        }
        else {
            echo "\nNO SERVER";
        }
    }

    public function onClose(Conn $conn)
    {
        echo "\nConnection {$conn->resourceId} has disconnected\n";

        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );
        if ( $connection )
        {
            $connection->closeConnections();
            $this->connections->detach($connection);
        }

    }

    public function onError(Conn $conn, \Exception $e)
    {
        echo "\nAn error has occurred: {$e->getMessage()}";

        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );
        if ( $connection )
        {
            $connection->closeConnections();
            $this->connections->detach($connection);
        }
    }

    public function getRep( $connId, $supportId, $is_representative = false ) {
        $is_representative = false;
        $representative;

        while ( !$is_representative ) {
            $ajax = new Ajax( "GET", "/support/getSupportRepresentative/$supportId" );
            $result = $ajax->send();

            if ( property_exists( $result, 'error' ) && $result->error) {
                $representative = $result;
            } else if ( property_exists( $result, 'status' ) && $result->status === "ok" && property_exists( $result, 'message' ) && $result->message->isRepresentative ) {
                $is_representative = true;
                $representative = $result->message->representative;
            } else {
                sleep($this->REPRESENTATIVE_INTERVAL_SECONDS * 1);
                echo "\nelse " . json_encode($result);
            }
        }

        $connection = Connection::findConnectionById( $this->connections, $connId );
        $connection->sendClientMessage( [ $is_representative ? 'representative' : 'error' => $representative, 'connectionID' => $connId ] );

        if ( $is_representative ) {
            echo "\nRep found for connection id: $connId - rep name: " . $representative->name;
        } else {
            echo "\nerror " . $representative;
        }
    }
}

$http_server_handler = function(ServerRequestInterface $request) {
    $data = json_decode( $request->getBody() );
    $response = new Response(
        501,
        array(
            'Content-Type' => 'text/plain'
        ),
        "NOT SET"
    );
    $connections = Configurator::read('connections');
    $response = function( $status, $data = null ) {
        return new Response( $status, array( 'Content-Type' => 'text/plain' ), gettype($data) !== "string" ? json_encode( $data ) : $data );
    };

    // echo "\nbody: " . json_encode($data);

    if ( $data && property_exists( $data, 'connectionID' ) ) {
        $connection = Connection::findConnectionById( $connections, $data->connectionID );

        if ( $connection ) {
            $connection->sendClientMessage($data);

            return $response( 200, $data );
        } else {
            echo "\nNO CONNECTION";

            return $response( 500, "NO CONNECTION" );
        }
    } else if ( $data && property_exists( $data, 'getConnectionID' ) && property_exists( $data, 'supportID' ) ) {
        $connection = Connection::findConnectionBySupportId( $connections, $data->supportID );

        echo "\nGETTING CONNECTION for support id: $data->supportID";
        if ( $connection ) {
            echo "\nRETURNING CONNECTION $connection->id for id: $data->supportID";

            return $response( 200, [ 'connectionID' => $connection->id ] );
        } else {
            echo "\nNO CONNECTION for support id: $data->supportID";

            return $response( 500, "NO CONNECTION" );
        }
    }  else if ( $data && property_exists( $data, 'message' ) && property_exists( $data->message, 'initial' ) && $data->message->initial ) {
        $connection = Connection::findConnectionBySupportId( $connections, $data->support->_id );

        if ( $connection ) {
            $connection->sendClientMessage( [ 'initialMessage' => true, 'message' => $data ] );

            return $response( 200, $data );
        } else {
            echo "\nNO CONNECTION for support id: $data->supportID";

            return $response( 500, "NO CONNECTION" );
        }
    } else {
        echo "\nReceived empty body: " . json_encode($data) . '.';

        return $response( 500, 'Received empty body: ' . json_encode( $data ) . '.' );
    }
};

$loop = ReactFactory::create();

Configurator::write('connections', new \SplObjectStorage);

$server = new IoServer(
    new HttpServer(
        new WsServer(
            new Chat( $loop )
        )
    ),
    new Reactor( '0.0.0.0' . ':' . 9000, $loop ),
    $loop
);

$http_server = new Server($http_server_handler);

$socket = new React\Socket\Server(9001, $loop);
$http_server->listen($socket);

$loop->run();
