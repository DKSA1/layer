<?php

namespace layer\core\http\websocket;

use layer\core\utils\Logger;

class WsClientConnection
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $ip;
    /**
     * @var int
     */
    private $port;
    /**
     * @var int
     */
    private $bufferSize;
    /**
     * @var resource
     */
    public $socket;
    /**
     * @var resource
     */
    private $master;
    /**
     * @var bool
     */
    private $handshake = false;
    /**
     * @var string
     */
    private $version;

    public function __construct($socket, $master, $bufferSize)
    {
        $this->id = uniqid();
        $this->socket = $socket;
        $this->master = $master;
        $this->bufferSize = $bufferSize;
        socket_set_nonblock($socket);
        socket_getpeername($socket, $this->ip, $this->port);
        Logger::write("Accepted client ".intval($this->socket)." connection from ip: ".$this->ip." with id: ".$this->id);
    }

    public function open()
    {
        $data = @socket_read($this->socket, $this->bufferSize);
        $bytes = strlen($data);
        if($bytes === false)
            Logger::write("Error: ".socket_last_error($this->socket));
        if(intval($bytes) == 0)
            return null;
        Logger::write("Handshaking headers from client ".intval($this->socket));
        if($this->performHandshake($data))
        {
            $this->handshake = true;
        }
    }

    private function performHandshake($headers)
    {
        if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $headers, $match))
        {
            $this->version = $match[1];
        }
        else
        {
            // The client doesn't support WebSocket
            return false;
        }
        if($this->version == 13)
        {
            // Extract header variables
            if(preg_match("/GET (.*) HTTP/", $headers, $match))
                $root = $match[1];
            if(preg_match("/Host: (.*)\r\n/", $headers, $match))
                $host = $match[1];
            if(preg_match("/Origin: (.*)\r\n/", $headers, $match))
                $origin = $match[1];
            if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match))
                $key = $match[1];

            $acceptKey = $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
            $acceptKey = base64_encode(sha1($acceptKey, true));

            $upgrade = "HTTP/1.1 101 Switching Protocols\r\n".
                "Upgrade: websocket\r\n".
                "Connection: Upgrade\r\n".
                "Sec-WebSocket-Accept: $acceptKey".
                "\r\n\r\n";

            socket_write($this->socket, $upgrade);
            Logger::write('Successful handshake with client: '.intval($this->socket). " websocket v".$this->version." connection established\n");
            return true;
        }
        else
        {
            // WebSocket version 13 required (the client supports version {$version})
            return false;
        }
    }

    public function send($message)
    {
        return @socket_write($this->socket, $this->encode($message));
    }

    public function receive()
    {
        $data = "";
        $len = 0;
        do
        {
            $read = @socket_read($this->socket, $this->bufferSize);
            if($read === false)
            {
                break;
            }
            if($read == "")
            {
                return false;
            }
            $len = strlen($read);
            $data .= $read;
        } while($len);
        if($len)
        {
            $decodedData = $this->decode($data);
            if($decodedData != "")
            {
                return $decodedData;
            }
        }

    }

    private function encode($text)
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCS', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCN', $b1, 127, $length);

        return $header.$text;
    }

    private function decode($payload)
    {
        echo $payload;

        $length = ord($payload[1]) & 127;

        if($length == 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        }
        elseif($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        }
        else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }

        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i%4];
        }

        /*if($text[0] == 0x88 || ($text[0] == 0xFF && $text[1] == 0x00))
        {
            echo "closing frame received\n";
            return false;
        }*/

        if(!mb_detect_encoding($text, 'UTF-8', true))
            return false;

        return $text;
    }

    public function close()
    {
        socket_close($this->socket);
        Logger::write("Connection with client ".intval($this->socket)." was closed");
    }

    /**
     * @return bool
     */
    public function isHandshake(): bool
    {
        return $this->handshake;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getIp()
    {
        return $this->ip;
    }
}