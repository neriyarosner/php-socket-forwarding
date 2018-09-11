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


class Chat implements MessageComponentInterface {
    protected $connections;
    protected $node_socket;

    private $loop;

    public function __construct( $loop )
    {
        $this->loop = $loop;
        $this->connections = new \SplObjectStorage;
    }

    public function onOpen( Conn $conn )
    {
        $this->createConnection($conn);
    }

    public function onMessage( Conn $from, $data )
    {
        // $msg = json_decode( $data );
        $numRecv = count( $this->connections ) - 1;
        $connection = Connection::findConnectionById( $this->connections, $from->resourceId );

        if ( $connection )
        {
            $connection->sendServerMessage( $data );
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

    public function onServerOpen(Conn $conn, $serverConnection)
    {
        $connection = new Connection( $conn->resourceId, $conn, $serverConnection );
        $connection->sendServerMessage( [ 'userID' => $conn->resourceId ] );

        $this->connections->attach( $connection );
    }

    public function onServerMessage( $msg, Conn $conn, $serverConnection )
    {
        $data = json_decode( $msg );
        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );

        if ( property_exists($data, 'userID') && $data->userID === $conn->resourceId )
        {
            $connection->sendClientMessage( [ 'init' => true, 'connectionId' => $data->userID ] );
        }
        elseif ( property_exists($data, 'status') && $data->status === 'ok' )
        {
            $connection->sendClientMessage( $data );
        }
        elseif ( property_exists($data, 'error') )
        {
            echo "\ngot error from server!";
            $connection->sendClientMessage( $data );
        }
    }

    public function onServerError( $e, Conn $conn )
    {
        echo "\nCould not connect: {$e->getMessage()}";

        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );

        $connection->closeConnections();
        // $this->loop->stop();
    }

    public function onServerClose( Conn $conn, $code, $reason )
    {
        echo "\nserverConnection closed ({$code} - {$reason})";

        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );
        $connection->closeConnections();
    }

    public function createConnection( Conn $conn )
    {
        $reactConnector = new React\Socket\Connector( $this->loop,
            [
                'dns' => '8.8.8.8',
                'timeout' => 10
            ]
        );
        $connector = new Ratchet\Client\Connector( $this->loop, $reactConnector );

        $connector( 'ws://127.0.0.1:8889', [], ['Origin' => 'http://localhost'] )
        ->then( function( Ratchet\Client\WebSocket $serverConnection ) use ( $conn )
            {
                $this->onServerOpen( $conn, $serverConnection );

                $serverConnection->on( 'message', function( MsgInterface $msg ) use ( $serverConnection, $conn )
                    {
                        $this->onServerMessage( $msg, $conn, $serverConnection );
                    }
                );

                $serverConnection->on( 'close', function( $code = null, $reason = null ) use ( $conn )
                    {
                        $this->onServerClose( $conn, $code, $reason );
                    }
                );
            }, function( \Exception $e ) use ( $conn )
            {
                $this->onServerError( $e, $conn );
            }
        );
    }
}

$loop = ReactFactory::create();

$server = new IoServer(
    new HttpServer(
        new WsServer(
            new Chat( $loop )
        )
    ),
    new Reactor( '0.0.0.0' . ':' . 9000, $loop ),
    $loop
);

$server->run();