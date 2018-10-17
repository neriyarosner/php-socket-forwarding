<?php
namespace Inc;

use Inc\Ajax;

class Connection {
    public $id;
    public $server;
    public $client;
    public $supportID;
    public $isServer = false;
    public $connectedTo;

    private $server_res;

    function __construct( $id, $client, $server = null, $supportID = null ) {
        $this->id = $id;
        $this->client = $client;
        $this->supportID = $supportID;
        $this->server = $server;
        $this->isServer = !!$server;
    }

    public function sendServerMessage( $message ) {
        if ( $message ) {
            $ajax = new Ajax("POST", '/support/sendMessage', null);
            $res = $ajax->send( gettype($message) === 'string' ? $message : json_encode($message) );
            $this->server_res = $res;
        }

        return $this;
    }

    public function sendServerInit( $connectionID, $data ) {
        if ( $connectionID && $data ) {
            $ajax = new Ajax("POST", '/support/socketInit', null);
            $res = $ajax->send( json_encode( [ 'supportID' => $data->supportID,  'init' => true, 'connectionID' => $connectionID, 'user' => $data->user ] ) );

            if ( property_exists( $res, 'error' ) && $res->error ) {
                echo "\nstatus: " . $res->status . ' in server init: ' . $res->message;
                return $res->error;
            }

            return $res;
        } else {
            echo "\nNo connection id " . $connectionID . " or data " . json_encode($data);
            return false;
        }
    }

    public function sendClientMessage( $message ) {
        if ( $this->client && $message ) {
            $this->client->send( gettype($message) === 'string' ? $message : json_encode($message) );
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

    public function setSupportID( $supportID ) {
        $this->supportID = $supportID;
    }

    public function isConnectionOpen(  ) {
        return $this->socket->readyState === 1;
    }

    public static function findConnectionById( \SplObjectStorage $connections, $id ) {
        $last_matching_connection = false;

        foreach ( $connections as $connection ) {
            if ( $id === $connection->id ) {
                $last_matching_connection = $connection;
                // return $connection;
            }
        }

        return $last_matching_connection;
    }

    public static function findConnectionBySupportId( \SplObjectStorage $connections, $supportID ) {
        $last_matching_connection = false;

        foreach ( $connections as $connection ) {

            if ( $supportID === $connection->supportID ) {
                $last_matching_connection = $connection;
                // return $connection;
            }
        }

        return $last_matching_connection;
    }

    public function getServerRes() {
        return $this->server_res;
    }
}