<?php
namespace Inc\Libs;

use Inc\Libs\Ajax;
use Inc\Libs\Output;

class Connection {
    public $phpConnectionId;
    public $nodeConnectionId;
    public $supportId;
    public $connectedTo;
    public $socket;

    public static $SOCKET_EVENTS = Array(
        "MISSIONS_INIT" => "innerChatInit",
        "CHAT_INIT" => "chatInit",
        "CHAT_MESSAGE" => "chatMessage",
        "SUPPORT_INIT" => "supportInit",
        "SUPPORT_MESSAGE" => "supportMessage",
        "CLIENT_MESSAGE" => "clientMessage",
        "MESSAGE_CALLBACK" => "messageCallback",
        "ERROR" => "error",
        "MESSAGE_READ" => "messageRead",
        "GET_REPRESENTATIVE" => "getRepresentative",
        "GET_CONNECTION_ID" => "getConnectionID",
        "FIND_AVAILABLE_REP" => "findAvailableRep"
    );

    function __construct( $phpConnectionId, $socket, $supportId = null ) {
        $this->phpConnectionId = $phpConnectionId;
        $this->socket = $socket;
        $this->supportId = $supportId;
    }

    function sendServerMessage( $message, $event ) {
        if ( $message ) {
            if ( gettype( $message ) !== "object" )
                $message = (object)$message;

            $message->event = $event;
            $message->nodeConnectionId = $this->nodeConnectionId;
            $message->phpConnectionId = $this->phpConnectionId;

            $url = "/dynamiChatApi/$event";

            if ($url) {
                $ajax = new Ajax("POST", $url, null);
                $res = $ajax->send( json_encode( $message ) );

                return $res;
            } else {
                Output::warning( "No implementation for event: $event" );
            }
        }
    }

    public function sendClientMessage( $message, $event ) {
        if ( gettype( $message ) === 'array' ) {
            $message['event'] = $event;
            $message['nodeConnectionId'] = $this->nodeConnectionId;
            $message['phpConnectionId'] = $this->phpConnectionId;
        }
        if ( gettype( $message ) === 'object' ) {
            $message->event = $event;
            $message->nodeConnectionId = $this->nodeConnectionId;
            $message->phpConnectionId = $this->phpConnectionId;
        }


        if ( $this->socket && $message ) {
            $this->socket->send( json_encode( $message ) );
        }

        return $this;
    }

    public function closeConnections() {
        if ( $this->socket ) {
            $this->socket->close();
        }

        return $this;
    }

    public function setSupportID( $supportId ) {
        $this->supportId = $supportId;
    }

    public function setNodeConnectionId( $nodeConnectionId ) {
        $this->nodeConnectionId = $nodeConnectionId;
    }

    public function isConnectionOpen() {
        // echo "\nclient: " . json_encode($this->socket);
        return true; // $this->socket->readyState === 1;
    }

    public static function findConnectionByNodeConnectionId( \SplObjectStorage $connections, $id ) {
        $defaultConnection = false;
        Output::extra_debug('findConnectionBySupportId - connections length: ' . count( $connections ) );

        foreach ( $connections as $connection ) {
            Output::extra_debug('Finding connection by id:', 'is same: ' . (($id == $connection->nodeConnectionId) ? 'true' : 'false'), "search id: $id", "iteration id: $connection->nodeConnectionId" );

            if ( $id == $connection->nodeConnectionId ) {
                return $connection;
            }
        }

        return $defaultConnection;
    }

    public static function findConnectionByPhpConnectionId( \SplObjectStorage $connections, $id ) {
        $defaultConnection = false;

        foreach ( $connections as $connection ) {
            if ( $id === $connection->phpConnectionId ) {
                return $connection;
            }
        }

        return $defaultConnection;
    }

    public static function findConnectionBySupportId( \SplObjectStorage $connections, $supportId ) {
        $last_matching_connection = false;
        Output::extra_debug('findConnectionBySupportId - connections length: ' . count( $connections ) );

        foreach ( $connections as $connection ) {
            Output::extra_debug('Finding connection by id:', 'is same: ' . (($supportId == $connection->supportId) ? 'true' : 'false'), "search id: " . $supportId, "iteration id: " . $connection->supportId, "is found: " . $last_matching_connection);

            if ( $supportId === $connection->supportId ) {
                $last_matching_connection = $connection;
            }
        }

        return $last_matching_connection;
    }
}
