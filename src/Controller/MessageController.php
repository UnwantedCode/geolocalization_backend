<?php
// src/Controller/MessageController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MessageController extends AbstractController
{
    public function sendMessage(Request $request, HubInterface $hub): JsonResponse
    {
        dd('asd');
        $update = new Update(
            "https://chat-group/messages/1",
            json_encode(['message' => 'Cześć!', 'user' => 'Jan Kowalski'])
        );
        $hub->publish($update);
        return new JsonResponse(['status' => 'Message sent']);
    }
}
