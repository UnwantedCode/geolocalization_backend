<?php
// src/Controller/MessageController.php
namespace App\Controller;

use App\Entity\Message;
use App\Repository\GroupRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

class MessageController extends AbstractController
{
    #[Route('/group/{groupId}/messages', name: 'get_group_messages', methods: ['GET'])]
    public function getMessages(int $groupId, MessageRepository $messageRepository): JsonResponse
    {
        // Pobierz wszystkie wiadomości dla danej grupy
        $messages = $messageRepository->findBy(['group' => $groupId], ['createdAt' => 'ASC']);

        // Przekształć wiadomości na tablicę JSON
        $data = array_map(function ($message) {
            return [
                'id' => $message->getId(),
                'user' => $message->getUser()->getUsername(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $messages);

        return $this->json($data);
    }

    #[Route('/group/{groupId}/message', name: 'add_group_message', methods: ['POST'])]
    public function addMessage(
        int $groupId,
        Request $request,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        EntityManagerInterface $entityManager,
        HubInterface $hub
    ): JsonResponse {
        // Pobierz dane z żądania
        $content = $request->request->get('content');
        $userId = $request->request->get('user_id');

        if (!$content || !$userId) {
            return $this->json(['error' => 'Missing content or user_id'], 400);
        }

        // Pobierz użytkownika i grupę
        $user = $userRepository->find($userId);
        $group = $groupRepository->find($groupId);

        if (!$user || !$group) {
            return $this->json(['error' => 'Invalid user or group'], 404);
        }

        // Zapisz wiadomość w bazie danych
        $message = new Message();
        $message->setUser($user);
        $message->setGroup($group);
        $message->setContent($content);
        $entityManager->persist($message);
        $entityManager->flush();

        // Opublikuj wiadomość w Mercure
        $update = new Update(
            "https://example.com/chat/$groupId", // Topic danej grupy
            json_encode([
                'id' => $message->getId(),
                'user' => $user->getUsername(),
                'content' => $content,
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            ])
        );
        $hub->publish($update);

        return $this->json(['status' => 'success', 'message' => 'Message sent']);
    }

}
