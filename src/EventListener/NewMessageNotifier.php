<?php

namespace App\EventListener;

use App\Entity\Message;
use App\WebSocket\ChatServer;
use Doctrine\ORM\Event\PostPersistEventArgs;
class NewMessageNotifier
{
    private ChatServer $chatServer;
    public function __construct(ChatServer $chatServer)
    {
        $this->chatServer = $chatServer;
    }


    public function postPersist(PostPersistEventArgs $args)
    {
        echo "Listener NewMessageNotifier został uruchomiony\n";
        $entity = $args->getObject();

        // Sprawdź, czy zapisywana encja to Message
        if (!$entity instanceof Message) {
            echo "Encja nie jest typu Message\n";
            return;
        }
        echo "Nowa wiadomość została zapisana: {$entity->getContent()}\n";
        // Przygotuj wiadomość w formacie JSON
        $data = json_encode([
            'id' => $entity->getId(),
            'user' => $entity->getUser()->getUsername(),
            'content' => $entity->getContent(),
            'createdAt' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        // echo all connected clients
        echo "ChatServer ID: " . $this->chatServer->getId() . "\n";
        $clients = $this->chatServer->getClients();
        echo "Klienci: " . count($clients) . "\n";

        foreach ($clients as $client) {
            $client->send($data);
            echo "Wysłano wiadomość do klienta\n";
        }
    }
}
