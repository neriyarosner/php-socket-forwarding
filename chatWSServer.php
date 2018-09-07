<?php
require __DIR__ . '/vendor/autoload.php';

use Inc\Connection;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface as Conn;

use Ratchet\RFC6455\Messaging\MessageInterface as MsgInterface;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

use React\EventLoop\Factory as ReactFactory;


use ElephantIO\Client as ElephantClient;
use ElephantIO\Engine\SocketIO\Version2X;
// use ElephantIO\Engine\AbstractSocketIO;

class Chat implements MessageComponentInterface {
    protected $connections;
    protected $node_socket;

    public function __construct() {
        $this->connections = new \SplObjectStorage;
    }

    public function onOpen( Conn $conn ) {
        echo "\ncreating connection";

        $this->createConnection($conn);
    }

    public function onMessage( Conn $from, $data ) {
        echo "\ngot message";

        // $msg = json_decode( $data );
        $numRecv = count( $this->connections ) - 1;
        $connection = Connection::findConnectionById( $this->connections, $from->resourceId );

        if ( $connection ) {
            echo sprintf( 'Connection %d sending message to %d server' . "\n", $from->resourceId, $numRecv );
            $connection->sendServerMessage( $data );
        }
        else {
            echo "\nNO SERVER";
        }
    }

    public function onClose(Conn $conn) {
        // The connection is closed, remove it, as we can no longer send it messages

        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );
        if ( $connection ) {
            $connection->closeConnections();

            $this->connections->detach($connection);
        }

        echo "\nConnection {$conn->resourceId} has disconnected\n";
    }

    public function onError(Conn $conn, \Exception $e) {
        echo "\nAn error has occurred: {$e->getMessage()}";

        Connection::findConnectionById( $this->connections, $conn->resourceId ).closeConnections();
    }

    public function createConnection( $conn ) {
        $loop = React\EventLoop\Factory::create();
        $reactConnector = new React\Socket\Connector( $loop, [
            'dns' => '8.8.8.8',
            'timeout' => 10
        ] );
        $connector = new Ratchet\Client\Connector( $loop, $reactConnector );

        $connector( 'ws://127.0.0.1:8889', [], ['Origin' => 'http://localhost'] )
        ->then( function( Ratchet\Client\WebSocket $serverConnection ) use ( $conn ) {
            echo "\nconnection created";

            $serverConnection->on( 'message', function( MsgInterface $msg ) use ( $serverConnection, $conn ) {
                echo "\ngot message from server: " . $msg;

                $data = json_decode( $msg );

                if ( $data->userID && $data->userID === $conn->resourceId ) {
                    $connection = new Connection( $conn->resourceId, $conn, $serverConnection );
                    $this->connections->attach( $connection );
                } elseif ( !$data->userID && $data->msg ) {
                    $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );
                    $connection->sendClientMessage( $data->msg );
                }
            } );

            $serverConnection->on( 'close', function( $code = null, $reason = null ) {
                echo "serverConnection closed ({$code} - {$reason})\n";
            } );

            $serverConnection->send( json_encode( [ 'userID' => $conn->resourceId ] ) );
        }, function( \Exception $e ) use ( $loop ) {
            echo "Could not connect: {$e->getMessage()}\n";

            $connection = new Connection( $conn->resourceId, $conn, null );
            $this->connections->attach( $connection );

            $loop->stop();
        });

        $loop->run();
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    9000
);

$server->run();
