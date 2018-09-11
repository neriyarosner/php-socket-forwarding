<?php
namespace Inc;

use Inc\Ajax;

class Connection {
    private $id;
    public $server;
    public $client;
    public $isServer = false;
    public $connectedTo;

    private $server_res;

    function __construct( $id, $client, $server ) {
        $this->id = $id;
        $this->client = $client;

        if ( $server ) {
            $this->server = $server;
            $this->isServer = true;
        }
    }

    public function sendServerMessage( $message ) {
        if ( $this->isServer && $this->server && $message ) {
            $this->server->send( json_encode( $message ) );
        }

        return $this;
    }

    public function sendClientMessage( $message ) {
        if ( $this->client && $message ) {
            $this->client->send( json_encode( $message ) );
        }

        return $this;
    }

    public function closeConnections() {
        if ( $this->server ) {
            $this->server->close();
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

    public function getServerRes() {
        return $this->server_res;
    }
}