<?php

namespace App\Command;

use App\WebSocket\ChatServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChatServerCommand extends Command
{
    protected static $defaultName = 'app:chat-server';
    private ChatServer $chatServer;
    public function __construct(ChatServer $chatServer)
    {
        parent::__construct();
        $this->chatServer = $chatServer; // Przypisz instancję ChatServer
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Uruchamianie serwera WebSocket...");
        $output->writeln("Uruchamianie serwera WebSocket z ChatServer ID: " . $this->chatServer->getId());
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    $this->chatServer
                )
            ),
            8080 // Port, na którym działa serwer
        );

        $output->writeln("Serwer WebSocket działa na ws://localhost:8080");
        $server->run();

        return Command::SUCCESS;
    }
}
