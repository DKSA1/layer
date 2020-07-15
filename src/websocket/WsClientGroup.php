<?php


namespace rloris\layer\websocket;


class WsClientGroup
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $id;
    /**
     * @var WsClientConnection[]
     */
    private $clients;

    public function __construct($name)
    {
        $this->id = uniqid('g');
        $this->name = $name;
        $this->clients = [];
    }

    public function contains(WsClientConnection $client)
    {
        $idx = array_search($client, $this->clients, true);
        if($idx === false)
            return -1;
        return $idx;
    }

    public function add(WsClientConnection $client)
    {
        if($this->contains($client) === -1)
            array_push($this->clients,$client);
    }

    public function remove(WsClientConnection $client)
    {
        $idx = $this->contains($client);
        if($idx >= 0)
        {
            array_splice($this->clients,$idx,1);
            return true;
        }
        return false;
    }

    public function send($message)
    {
        foreach ($this->clients as $client)
        {
            if($client->send($message) === false)
            {
                echo "Connection already closed\n";
                $client->close();
            }
        }
    }

    public function close()
    {
        foreach ($this->clients as $client)
        {
            $client->close();
        }
    }

    public function size()
    {
        return count($this->clients);
    }

    public function getClients()
    {
        return $this->clients;
    }
}