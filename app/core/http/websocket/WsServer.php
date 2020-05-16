<?php

namespace layer\core\http\websocket;

use layer\core\utils\Logger;

class WsServer
{
    /**
     * @var int
     */
    private $maxConnections = 100;
    /**
     * @var int
     */
    private $bufferSize = 2048;
    /**
     * @var int
     */
    private $port = 8000;
    /**
     * @var string
     */
    private $address = '127.0.0.1';
    /**
     * @var WsClientGroup
     */
    private $clients;
    /**
     * @var WsClientGroup[]
     */
    private $groups;
    /**
     * @var resource
     */
    private $master;

    public static function create($address, $port, $bufferSize = 2048, $maxConnections = 100)
    {
        if(!self::isRunning($address, $port))
        {
            return new WsServer($address, $port, $bufferSize, $maxConnections);
        } else
            return null;
    }

    private static function isRunning($address, $port)
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(@socket_connect($socket, $address, $port))
        {
            socket_close($socket);
            echo 'Server already running on that address and port';
            return true;
        }
        return false;

    }

    public function __construct($address, $port, $bufferSize, $maxConnections)
    {
        set_time_limit(0);
        $this->address = $address;
        $this->port = $port;
        $this->bufferSize = $bufferSize;
        $this->maxConnections = $maxConnections;
        // ignore_user_abort(true);
        $this->clients = new WsClientGroup("");
        $this->groups = [];
    }

    public function start()
    {
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!is_resource($this->master))
            Logger::write("socket_create() failed: ".socket_strerror(socket_last_error()));

        if (!socket_bind($this->master, $this->address, $this->port))
            Logger::write("socket_bind() failed: ".socket_strerror(socket_last_error()));

        if(!socket_listen($this->master, 20))
            Logger::write("socket_listen() failed: ".socket_strerror(socket_last_error()));

        Logger::write("[".date('Y-m-d H:i:s')."] Websocket server started on ".$this->address.":".$this->port);

        socket_set_nonblock($this->master);

        $this->run();
    }

    private function run()
    {
        $run = true;
        while($run)
        {
            // check for new connections
            $this->checkNewIncomingConnections();
            // check for client changes
            $this->checkClientChanges();
        }
        $this->stop();
    }

    private function checkNewIncomingConnections()
    {
        $write = null;
        $except = null;
        $changed = [$this->master];
        $changes = socket_select($changed, $write, $except, 0);
        if($changes != 0)
        {
            Logger::write("New client connection detected");
            $clientSocket = socket_accept($this->master);
            if($this->clients->size() >= $this->maxConnections)
            {
                socket_close($clientSocket);
                echo "Connection refused too many clients\n";
                return;
            }
            if($clientSocket<0)
            {
                Logger::write("socket_accept() failed");
            }
            else if($clientSocket !== false)
            {
                Logger::write("Connecting socket");
                $c = new WsClientConnection($clientSocket, $this->master, $this->bufferSize);
                $msg = "[".Date('Y-m-d h:m:s')."][".$c->getId()."]: New client joined - ".($this->clients->size()+1)."/".$this->maxConnections;
                echo $msg."\n";
                $this->clients->send($msg);

                $this->clients->add($c);
                Logger::write("Total clients: ".$this->clients->size());
            }
        }
    }

    private function checkClientChanges()
    {
        foreach ($this->clients->getClients() as $client)
        {
            if(!$client->isHandshake())
            {
                $client->open();
            }
            else
            {
                if(($data = $client->receive()) !== false)
                {
                    if($data != "")
                    {
                        $msg = "[".Date('Y-m-d h:m:s')."][".$client->getId()."]: ".$data;
                        echo $msg."\n";
                        $this->clients->send($msg);
                    }
                }
                else
                {
                    if($this->clients->remove($client))
                    {
                        $msg = "[".Date('Y-m-d h:m:s')."][".$client->getId()."]: Client Connection closed\n";
                        echo $msg."\n";
                        $this->clients->send($msg);
                        $client->close();
                        break;
                    }
                }
            }
        }
    }

    public function stop()
    {
        $this->clients->close();
        socket_close($this->master);
        Logger::write("[".date('Y-m-d H:i:s')."] Websocket server stopped");
    }
}