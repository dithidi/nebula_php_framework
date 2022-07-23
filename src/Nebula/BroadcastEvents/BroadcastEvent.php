<?php

namespace Nebula\BroadcastEvents;

use Nebula\Jobs\Job;
use ZMQ;
use ZMQContext;

class BroadcastEvent extends Job
{
    /**
     * Broadcasts an event with payload to tcp server.
     *
     * @param array $data The payload data for the tcp server.
     * @return void
     */
    protected function broadcast($data)
    {
        $context = new ZMQContext();
        $socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'socketServer');
        $socket->connect("tcp://127.0.0.1:5555");

        $socket->send(json_encode($data));
    }
}
