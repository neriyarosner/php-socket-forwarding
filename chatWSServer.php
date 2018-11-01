<?php
require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Inc\Libs\Configurator;
use Inc\Libs\Output;

use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;

use Psr\Http\Message\ServerRequestInterface;

use React\EventLoop\Factory as ReactFactory;
use React\Socket\Server;
use React\Socket\SecureServer;

Configurator::write('connections', new \SplObjectStorage);

$loop = ReactFactory::create();

$socket_uri = '0.0.0.0' . ':' . 9000;
$http_uri = '0.0.0.0' . ':' . 9001;

$socket_server = setupSocketServer( $loop, $socket_uri );
$http_server = setupHttpServer( $loop, $http_uri );

Output::info('ServerAPI Listening on ' . str_replace('tcp:', 'http:', $http_uri) . PHP_EOL . '[+] ClientApi Listening on ' . str_replace('tcp:', 'ws:', $socket_uri));

$loop->run();

function setupSocketServer( $loop, $socket_uri ) {
    $socket = setHttps( new Server( $socket_uri, $loop ), $loop );

    $server = new IoServer(
        new HttpServer(
            new WsServer(
                new Inc\Api\ClientApi( $loop )
            )
        ),
        $socket,
        $loop
    );

    return $server;
}

function setupHttpServer( $loop, $http_uri ) {
    $http = setHttps( new Server( $http_uri, $loop ), $loop );

    // Set up our WebSocket server for clients wanting real-time updates
    $http_server = new React\Http\Server(function (Psr\Http\Message\ServerRequestInterface $request) {
        $connections = Configurator::read('connections');

        $handler = new Inc\Api\ServerApi( $connections, $request );

        return $handler->getResponse();
    });

    $http_server->listen($http);

    return $http_server;
}

function setHttps( $socketServer, $loop ) {
    $isHttps = false;

    if ( $isHttps ) {
        $socketServer = new SecureServer($socket, $loop, [
            'local_cert'        => './certs/server.crt', // path to your cert
            'local_pk'          => './certs/server.key', // path to your server private key
            'allow_self_signed' => TRUE, // Allow self signed certs (should be false in production)
             'verify_peer' => FALSE
        ]);
    }

    return $socketServer;
}