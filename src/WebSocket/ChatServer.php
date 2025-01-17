<?php

namespace App\WebSocket;

use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ChatServer implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    private $entityManager;
    private $serializer;
    private string $id;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->id = uniqid('chat_server_', true);
        $this->clients = new \SplObjectStorage();
        $this->entityManager = $entityManager;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "Nowe połączenie: {$conn->resourceId}. Klientów: " . count($this->clients) . "\n";
        echo "ChatServer ID: " . $this->getId() . "\n";
        // Wczytaj poprzednie wiadomości dla grupy
        $queryParams = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryParams, $params);
        $groupId = $params['groupId'] ?? null;

        if ($groupId) {
            $messages = $this->entityManager->getRepository('App\Entity\Message')
                ->findBy(['group' => $groupId], ['createdAt' => 'ASC']);

            foreach ($messages as $message) {
                $conn->send(json_encode([
                    'id' => $message->getId(),
                    'user' => $message->getUser()->getUsername(),
                    'content' => $message->getContent(),
                    'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                ]));
            }
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Otrzymano wiadomość: $msg\n";

        // Parsowanie wiadomości JSON
        $data = json_decode($msg, true);

        if (isset($data['user_id'], $data['group_id'], $data['content'])) {
            // Utwórz nową wiadomość
            $message = new Message();
            $message->setUser($this->entityManager->getReference('App\Entity\User', $data['user_id']));
            $message->setGroup($this->entityManager->getReference('App\Entity\Group', $data['group_id']));
            $message->setContent($data['content']);

            // Zapisz wiadomość do bazy
            $this->entityManager->persist($message);
            $this->entityManager->flush();

            echo "Zapisano wiadomość do bazy: {$message->getContent()}\n";

            // Prześlij wiadomość do wszystkich klientów
            $response = json_encode([
                'id' => $message->getId(),
                'user' => $data['user_id'],
                'group' => $data['group_id'],
                'content' => $data['content'],
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            foreach ($this->clients as $client) {
                $client->send($response);
            }
        } else {
            echo "Błędny format wiadomości\n";
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Połączenie zamknięte: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Błąd: {$e->getMessage()}\n";
        $conn->close();
    }

    public function getClients()
    {
        return $this->clients;
    }



    public function getId(): string
    {
        return $this->id;
    }
}
