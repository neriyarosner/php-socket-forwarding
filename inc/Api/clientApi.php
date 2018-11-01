<?php
namespace Inc\Api;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface as Conn;

use Inc\Libs\Configurator;
use Inc\Libs\Output;
use Inc\Libs\Connection;

class ClientApi implements MessageComponentInterface {
    protected $node_socket;
    protected $connections;
    protected $REPRESENTATIVE_INTERVAL_SECONDS = 5;

    private $loop;

    public function __construct( $loop )
    {
        $this->loop = $loop;
        $this->connections = Configurator::read('connections');
    }

    public function onOpen( Conn $conn )
    {

    }

    public function onMessage( Conn $conn, $data )
    {
        // $msg = json_decode( $data );
        $numRecv = count( $this->connections ) - 1;
        // $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );

        if ( $data )
        {
            $parsedData = json_decode( $data );

            $events = gettype($parsedData->event) === 'array' ? $parsedData->event : [$parsedData->event];

            foreach ($events as $event) {
                Output::info('Client event: ' . $event);

                switch($event) {
                    case Connection::$SOCKET_EVENTS['SUPPORT_INIT']:
                        Output::debug('received init from client', 'support id: ' . $parsedData->support->_id, "conn: $conn->resourceId");
                        $this->onSocketSupportInit( $conn, $parsedData );
                        break;

                    case Connection::$SOCKET_EVENTS['CLIENT_MESSAGE']:
                        Output::debug('received client message from client', 'chat id: ' . $parsedData->chat->chatId);
                        $this->onSocketClientMessage( $conn, $parsedData );
                        break;

                    case Connection::$SOCKET_EVENTS['MESSAGE_READ']:
                        Output::debug('received message read from client', 'chat id: ' . $parsedData->chat->id);
                        $this->onSocketMessageRead( $conn, $parsedData );
                        break;
                }
            }
        }
        else {
            Output::error('NO DATA');
        }
    }

    public function onClose(Conn $conn)
    {
        Output::info("Connection $conn->resourceId has disconnected");

        $connection = Connection::findConnectionByPhpConnectionId( $this->connections, $conn->resourceId );
        if ( $connection )
        {
            $connection->closeConnections();
            $this->connections->detach($connection);
        }

    }

    public function onError(Conn $conn, \Exception $e)
    {
        Output::error("An error has occurred:", $e->getMessage());

        $connection = Connection::findConnectionById( $this->connections, $conn->resourceId );
        if ( $connection )
        {
            $connection->closeConnections();
            $this->connections->detach($connection);
        }
    }

    /**
     * create a new connection to client and save it.
     * send node server the client connection id (phpConnectionID) and supportId.
     * send back to client with SUPPORT_INIT event the node and php ConnectionIds.
     * @param data contains: {support: ISupport, user: IUser}
     */
    private function onSocketSupportInit( Conn $conn, $data )
    {
        $connection = new Connection( $conn->resourceId, $conn, $data->support->_id );

        $nodeResponse = $connection->sendServerMessage( [ 'supportId' => $data->support->_id, 'phpConnectionId' => $conn->resourceId, 'user' => $data->user ], Connection::$SOCKET_EVENTS["SUPPORT_INIT"] );

        if ( !$nodeResponse ) {
            Output::error("nodeResponse is not defined:", $nodeResponse);
            $nodeResponse = (object)[ 'nodeConnectionId' => null ];
        }

        if (property_exists( $nodeResponse, 'error' ) && $nodeResponse->error) {
            Output::error("nodeResponse error: ", $nodeResponse->message);
            $nodeResponse = (object)[ 'nodeConnectionId' => null ];
        }

        $connection->setNodeConnectionId( $nodeResponse->nodeConnectionId );
        $connection->sendClientMessage( [ 'phpConnectionId' => $conn->resourceId, 'nodeConnectionId' => $nodeResponse->nodeConnectionId ], Connection::$SOCKET_EVENTS["SUPPORT_INIT"] );

        $this->connections->attach( $connection );
    }

    private function onSocketClientMessage( Conn $conn, $data )
    {
        $connection = Connection::findConnectionByPhpConnectionId( $this->connections, $conn->resourceId );
        $nodeResponse = $connection->sendServerMessage( $data, Connection::$SOCKET_EVENTS["CLIENT_MESSAGE"] );

        if (property_exists( $nodeResponse, 'error' ) && $nodeResponse->error) {
            Output::error("nodeResponse error: ", $nodeResponse->message);
        }

        $connection->sendClientMessage( $nodeResponse, Connection::$SOCKET_EVENTS["CLIENT_MESSAGE"] );
    }

    private function onSocketMessageRead( Conn $conn, $data )
    {
        $connection = Connection::findConnectionByPhpConnectionId( $this->connections, $conn->resourceId );
        $nodeResponse = $connection->sendServerMessage( $data, Connection::$SOCKET_EVENTS["MESSAGE_READ"] );

        if (property_exists( $nodeResponse, 'error' ) && $nodeResponse->error) {
            Output::error("nodeResponse error: ", $nodeResponse->message);
        }
    }
}