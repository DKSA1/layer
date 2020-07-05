<?php

namespace layer\core\http\websocket;

use layer\core\config\Configuration;
use layer\core\utils\Logger;
use layer\core\utils\StringValidator;

class WsServer
{
    /**
     * @var int
     */
    private $maxConnections;
    /**
     * @var int
     */
    private $bufferSize;
    /**
     * @var int
     */
    private $port;
    /**
     * @var string
     */
    private $address;
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
    /**
     * @var bool
     */
    private $started = false;
    /**
     * @var float
     */
    private $startTime;
    /**
     * @var bool
     */
    private $running = false;
    /**
     * @var int
     */
    private $pid;

    public static function create($remote = '127.0.0.1', $port = 8000, $bufferSize = 2048, $maxConnections = 10)
    {
        if(StringValidator::isDomain($remote) && !StringValidator::isIpv4($remote))
        {
            putenv('RES_OPTIONS=retrans:1 retry:1 timeout:1 attempts:1');
            $remote = gethostbyname($remote.'.');
        }
        if(StringValidator::isIpv4($remote))
        {
            if(!self::isRunning($remote, $port))
            {
                return new WsServer($remote, $port, $bufferSize, $maxConnections);
            }
        }
        return null;
    }

    private static function isRunning($address, $port)
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(@socket_connect($socket, $address, $port))
        {
            socket_close($socket);
            Logger::write('Server already running on that address and port');
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
        $this->pid = getmypid();
        // ignore_user_abort(true);
        $this->clients = new WsClientGroup("");
        $this->groups = [];
    }

    public function start()
    {
        if(!$this->started) {
            $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);

            if (!is_resource($this->master))
                Logger::write("socket_create() failed: ".socket_strerror(socket_last_error()));

            if (!socket_bind($this->master, $this->address, $this->port))
                Logger::write("socket_bind() failed: ".socket_strerror(socket_last_error()));

            if(!socket_listen($this->master, 20))
                Logger::write("socket_listen() failed: ".socket_strerror(socket_last_error()));

            Logger::write("Websocket server started on ".$this->address.":".$this->port);

            socket_set_nonblock($this->master);

            $this->started = true;
            $this->startTime = microtime(true);

            $this->run();
        }
    }

    private function run()
    {
        $this->running = true;
        do
        {
            $this->checkNewIncomingConnections();
            $this->checkClientChanges();
        }
        while($this->running);
        $this->close();
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
                $msg = "[".Date('Y-m-d h:m:s')."][".$c->getId()."][".$c->getIp()."]: New client joined - ".($this->clients->size()+1)."/".$this->maxConnections;
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
                        $msg = "[".Date('Y-m-d h:m:s')."][".$client->getId()."][".$client->getIp()."]: ".$data;
                        echo $msg."\n";
                        $this->clients->send($msg);
                    }
                }
                else
                {
                    if($this->clients->remove($client))
                    {
                        $msg = "[".Date('Y-m-d h:m:s')."][".$client->getId()."][".$client->getIp()."]: Client Connection closed";
                        echo $msg."\n";
                        $this->clients->send($msg);
                        $client->close();
                        break;
                    }
                }
            }
        }
    }

    private function close()
    {
        $this->clients->close();
        socket_close($this->master);
        $this->started = false;
    }

    public function stop()
    {
        $this->running = false;
        Logger::write("[".date('Y-m-d H:i:s')."] Websocket server stopped");
    }
}