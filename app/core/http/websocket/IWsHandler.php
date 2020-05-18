<?php

namespace layer\core\http\websocket;

interface IWsHandler
{
    // server is started
    function onStart(WsServer $server);
    // new connection detected : socket opened
    function onConnection(WsClientConnection $client);
    // connected, implement this for handshake override, open the websocket, called on first message from client
    function open(WsClientConnection $client, string $headers);
    // called before onMessage, handshake was done
    function decode($data): string;
    // new message detected, handshake was done
    function onMessage(WsClientConnection $client, string $message);
    // called before sending message to client, handshake was done
    function encode($message) : string;
    // called before closing socket with client
    function close(WsClientConnection $client);
    // new disconnection detected : socket closed
    function onDisconnection(WsClientConnection $client);
    // server is stopped
    function onStop(WsServer $server);
    // error with server, client, group...
    function onError($error);
}