<?php
namespace Inc;

class Connection {
    private $id;
    public $server;
    public $client;
    public $isServer = false;
    public $connectedTo;

    function __construct( $id, $client, $server ) {
        $this->id = $id;
        $this->client = $client;

        if ( $server ) {
            $this->server = $server;
            $this->isServer = true;
        }
    }

    public function sendServerMessage( $message ) {
        echo "sending message to server";
        if ( $this->isServer && $this->server && $message ) {
            $this->server->send([ 'data' => $message ] );
        }

        return $this;
    }

    public function sendClientMessage( $message ) {
        echo "sending message to client";

        if ( $this->client && $message ) {
            $this->client->send([ 'data' => $message ] );
        }

        return $this;
    }

    public function closeConnections() {
        // if ( $this->server ) {
        //     $this->server->close();
        // }

        if ( !$this->isServer ) {
            // self::findConnectionById();
        }

        if ( $this->client ) {
            $this->client->close();
        }

        return $this;
    }

    public function attachServer( $serverID ) {
        $this->isServer = true;
        $this->connectedTo = $serverID;
        $this->server = self::findConnectionById( $serverID );
    }

    public function setIsServer( $isServer ) {
        $this->isServer = $isServer;
    }

    public static function findConnectionById( $connections, $id ) {
        foreach ( $connections as $connection ) {
            if ( $id === $connection->id ) {
                return $connection;
            }
        }
        foreach ( $connections as $connection ) {
        }

        return false;
    }
}