<?php
require __DIR__ . '/vendor/autoload.php';

use Inc\Connection;

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

    private $loop;

    public function __construct( $loop )
    {
        $this->loop = $loop;
        $this->connections = Configurator::read('connections');
    }

    public function onOpen( Conn $conn )
    {
        $connection = new Connection( $conn->resourceId, $conn, null);
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

            if ( property_exists( $parsedData, 'init' ) && $parsedData->init ) {
                echo "\nrecieved init from client and passing to server " . $from->resourceId;
                $connection->sendServerInit( $from->resourceId, $parsedData );
            } else {
                $connection->sendServerMessage( $data );
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
}

$http_server_handler = function(ServerRequestInterface $request) {
    $body = $request->getBody();
    $data = json_decode( $body );

    if ( $data && property_exists( $data, 'connectionID' ) ) {
        $connections = Configurator::read('connections');
        $connection = Connection::findConnectionById( $connections, $data->connectionID );

        if ($connection) {
            $connection->sendClientMessage($data);
        } else {
            echo "\nNO CONNECTION";
        }
    } else {
        echo "\nReceived empty body: " . $body . '.';
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        )
    );
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
