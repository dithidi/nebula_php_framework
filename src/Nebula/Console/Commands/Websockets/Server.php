<?php

namespace Nebula\Console\Commands\Websockets;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Wamp\WampServer;
use React\EventLoop\Factory;
use React\ZMQ\Context;
use React\Socket\Server as SocketServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Server extends Command
{
    /**
     * The command name for the console interface.
     *
     * @var string
     */
    protected static $defaultName = 'websockets:server';

    /**
     * Executes the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param Symfony\Component\Console\Input\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loop   = Factory::create();
        $socketServer = new \Nebula\Websockets\Server;

        // Listen for the web server to make a ZeroMQ push after an ajax request
        $context = new Context($loop);
        $pull = $context->getSocket(\ZMQ::SOCKET_PULL);
        $pull->bind('tcp://127.0.0.1:5555'); // Binding to 127.0.0.1 means the only client that can connect is itself
        $pull->on('message', array($socketServer, 'onBroadcast'));

        // Set up our WebSocket server for clients wanting real-time updates
        $webSock = new SocketServer('0.0.0.0:8080', $loop); // Binding to 0.0.0.0 means remotes can connect
        $webServer = new IoServer(
            new HttpServer(
                new WsServer(
                    new WampServer(
                        $socketServer
                    )
                )
            ),
            $webSock
        );

        $loop->run();
    }
}
