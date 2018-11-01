<?php
namespace Inc\Api;

use Inc\Libs\Configurator;
use Inc\Libs\Output;
use Inc\Libs\Connection;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;

class ServerApi
{
    protected $connections;
    protected $response;

    public function __construct( $connections, $request )
    {
        $data = json_decode( $request->getBody() );

        Output::info( "Node event: " . $data->event ); //, "Node data: " . json_encode( $data ) );

        if ( count( $connections ) <= 0 ) {
            Output::warning( "There are no active connections" );
        }

        $this->connections = $connections;

        if ( $data && property_exists( $data, 'event' ) ) {
            $events = $data->event;

            if ( gettype( $events ) !== 'array' )
                $events = [ $events ];

            foreach ($events as $event) {
                $this->handleEvent( $data, $event );
            }
        } else {
            $this->responseError( 'noData', 'got socket message without data or event', $data );
        }
    }

    static function requestHandler( ServerRequestInterface $request )
    {
        $connections = Configurator::read('connections');
        $data = json_decode( $request->getBody() );

        $handler = new self( $connections, $data );

        return $handler->getResponse();
    }

    function handleEvent( $data, $event ) {
        switch ($event) {
            case Connection::$SOCKET_EVENTS['SUPPORT_INIT']:
                return $this->onSupportInit( $data );
                break;

            case Connection::$SOCKET_EVENTS['GET_CONNECTION_ID']:
                return $this->onGetConnectionId( $data );
                break;

            case Connection::$SOCKET_EVENTS['SUPPORT_MESSAGE']:
                return $this->onSocketMessage( $data );
                break;

            case Connection::$SOCKET_EVENTS['GET_REPRESENTATIVE']:
                return $this->onGetRepresentative( $data );
                break;

            case Connection::$SOCKET_EVENTS["FIND_AVAILABLE_REP"]:
                return $this->onSocketFindRep( $data );
                break;

            case Connection::$SOCKET_EVENTS["MESSAGE_READ"]:
                return $this->onSocketMessageRead( $data );
                break;

            default:
                Output::error( "NO EVENT Found $data->event - Data:", $data );

                return $this->responseError( 'noEventFound', 'there is no such event: ' . $data->event );
                break;
        }
    }

    function onGetConnectionId( $data )
    {
        $this->getConnection( $data->nodeConnectionId, function( $connection )  use ( $data ) {
            Output::info( "RETURNING CONNECTION $connection->phpConnectionId: ", 'supportId: ' . $data->nodeConnectionId );

            return $this->responseOk( [ 'phpConnectionId' => $connection->phpConnectionId ] );
        });
    }

    /**
     * initiate connection and get nodeConnectionId.
     *
     * receive data from node backend, and pass it to client.
     *
     * @param data: {nodeConnectionId: number, support: ISupport}
     * @return phpConnectionId: number - in response to node.
     */
    function onSupportInit( $data )
    {
        $connection = Connection::findConnectionBySupportId( $this->connections, $data->support->_id );

        if ( $connection ) {
            Output::debug( "Sending init to client", "nodeConnectionId: $data->nodeConnectionId", "phpConnectionId: $connection->phpConnectionId" );

            $connection->setNodeConnectionId( $data->nodeConnectionId );
            $connection->sendClientMessage( $data, Connection::$SOCKET_EVENTS[ "SUPPORT_INIT" ] );

            return $this->responseOk( [ 'phpConnectionId' => $connection->phpConnectionId ] );
        } else {
            $errorMessage = "No connection for support id: " . $data->support->_id;

            return $this->responseError( 'noConnection', $errorMessage );
        }
    }

    function onSocketMessage( $data )
    {
        $this->getConnection( $data->nodeConnectionId, function( $connection )  use ( $data ) {
            $connection->sendClientMessage( json_decode( json_encode( $data ) ), Connection::$SOCKET_EVENTS[ "SUPPORT_MESSAGE" ] );

            return $this->responseOk( [ 'phpConnectionId' => $connection->phpConnectionId ] );
        });
    }

    function onGetRepresentative( $data )
    {
        echo "funnest gett reps " . json_encode($data);
    }

    function onSocketFindRep( $data )
    {
        $this->getConnection( $data->nodeConnectionId, function( $connection )  use ( $data ) {
            $connection->sendClientMessage( json_decode( json_encode( $data ) ), Connection::$SOCKET_EVENTS[ "FIND_AVAILABLE_REP" ] );

            return $this->responseOk( [ 'status' => 'ok' ] );
        });
    }

    /**
     * Transfer new message to client
     *
     * @param data contains: {chat: IChat, support: ISupport}
     */
    function onSocketMessageRead( $data )
    {
        $this->getConnection( $data->nodeConnectionId, function( $connection )  use ( $data ) {
            $connection->sendClientMessage( json_decode( json_encode( $data ) ), Connection::$SOCKET_EVENTS[ "MESSAGE_READ" ] );

            return $this->responseOk( [ 'status' => 'ok' ] );
        });
    }

    function getConnection( $nodeConnectionId, $cb ) {
        Output::debug("Getting connection by node connection id: " . $nodeConnectionId);

        $connection = Connection::findConnectionByNodeConnectionId( $this->connections, $nodeConnectionId );

        if ( $connection ) {
            $cb( $connection );
        } else {
            $errorMessage = "No connection for node connection id: " . $nodeConnectionId;

            return $this->responseError( 'noConnection', $errorMessage );
        }
    }

    function responseOk( $data ) {
        $data = gettype( $data ) !== "string" ? json_encode( $data ) : $data;
        $this->response = new Response( 200, array( 'Content-Type' => 'text/plain' ), $data );
    }

    function responseError( $status, $message, $extraData = null ) {
        $data = [ 'error' => true, 'status' => $status, 'message' => $message ];

        if ( $extraData ) {
            $data['data'] = $extraData;
            Output::error( "ERROR DATA: ", json_encode( $extraData ) );
        }

        Output::error( "error: ", json_encode( $data ) );

        $this->response = new Response( 500, array( 'Content-Type' => 'text/plain' ), json_encode( $data ) );

        return $data;
    }

    function get_calling_function() {
        $caller = debug_backtrace();
        $caller = $caller[2];
        $r = $caller['function'] . '()';

        if ( isset( $caller['class'] ) ) {
          $r .= ' in ' . $caller['class'];
        }

        if ( isset( $caller['object'] ) ) {
          $r .= ' (' . get_class($caller['object']) . ')';
        }

        return $r;
    }

    function getResponse() {
        return $this->response;
    }
}
