<?php

namespace App\Controller\Api;

use App\Dto\MessageDTO;
use App\Entity\Message;
use App\Repository\GroupRepository;
use App\Repository\MessageRepository;
use App\Service\MessageNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/messages-custom', name: 'api_messages_custom_')]
class MessageController extends AbstractController
{
    public function __construct(
        private MessageRepository $messageRepo,
        private GroupRepository $groupRepo,
        private EntityManagerInterface $em,
        private Security $security,
        private MessageNotificationService $notificationService
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $groupId = $request->query->get('group_id');
        $limit = (int) ($request->query->get('limit', 50));
        $offset = (int) ($request->query->get('offset', 0));

        // Walidacja group_id
        if (!$groupId) {
            return $this->json(['error' => 'group_id is required'], Response::HTTP_BAD_REQUEST);
        }

        // Walidacja limitu
        if ($limit < 1 || $limit > 200) {
            return $this->json(['error' => 'limit must be between 1 and 200'], Response::HTTP_BAD_REQUEST);
        }

        // Sprawdzenie czy grupa istnieje
        $group = $this->groupRepo->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie członkostwa (pattern z JoinGroupController)
        if (!$group->getUsers()->contains($user)) {
            return $this->json(['error' => 'You are not a member of this group'], Response::HTTP_FORBIDDEN);
        }

        // Pobranie wiadomości
        $messages = $this->messageRepo->findGroupMessages($groupId, $limit, $offset);

        // Mapowanie na DTO (pattern z UserGroupLocationController)
        $output = [];
        foreach ($messages as $message) {
            $dto = new MessageDTO();
            $dto->id = $message->getId();
            $dto->userId = $message->getUser()->getId();
            $dto->username = $message->getUser()->getUsername();
            $dto->userAvatar = $message->getUser()->getAvatar();
            $dto->groupId = $message->getGroup()->getId();
            $dto->content = $message->getContent();
            $dto->createdAt = $message->getCreatedAt()->format('Y-m-d H:i:s');
            $dto->updatedAt = $message->getUpdatedAt()->format('Y-m-d H:i:s');
            $output[] = $dto;
        }

        return $this->json($output, Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $data = json_decode($request->getContent(), true);

        $groupId = $data['group'] ?? null;
        $content = $data['content'] ?? null;

        // Walidacja content
        if (!$content || trim($content) === '') {
            return $this->json(['error' => 'content is required and cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($content) > 1000) {
            return $this->json(['error' => 'content cannot exceed 1000 characters'], Response::HTTP_BAD_REQUEST);
        }

        // Walidacja group
        if (!$groupId) {
            return $this->json(['error' => 'group is required'], Response::HTTP_BAD_REQUEST);
        }

        $group = $this->groupRepo->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie członkostwa
        if (!$group->getUsers()->contains($user)) {
            return $this->json(['error' => 'You are not a member of this group'], Response::HTTP_FORBIDDEN);
        }

        // Utworzenie wiadomości
        $message = new Message();
        $message->setUser($user);
        $message->setGroup($group);
        $message->setContent(trim($content));

        $this->em->persist($message);
        $this->em->flush();

        // Wysłanie notyfikacji (asynchroniczne - nie czekamy na wynik)
        try {
            $this->notificationService->sendGroupMessageNotification($group, $user, $content);
        } catch (\Throwable $e) {
            // Logowanie błędu, ale nie przerywamy zwracania odpowiedzi
            // TODO: proper logging
        }

        // Mapowanie na DTO
        $dto = new MessageDTO();
        $dto->id = $message->getId();
        $dto->userId = $message->getUser()->getId();
        $dto->username = $message->getUser()->getUsername();
        $dto->userAvatar = $message->getUser()->getAvatar();
        $dto->groupId = $message->getGroup()->getId();
        $dto->content = $message->getContent();
        $dto->createdAt = $message->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $message->getUpdatedAt()->format('Y-m-d H:i:s');

        return $this->json($dto, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $message = $this->messageRepo->find($id);

        if (!$message) {
            return $this->json(['error' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie ownership
        if ($message->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'You can only edit your own messages'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $newContent = $data['content'] ?? null;

        // Walidacja content
        if (!$newContent || trim($newContent) === '') {
            return $this->json(['error' => 'content is required and cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($newContent) > 1000) {
            return $this->json(['error' => 'content cannot exceed 1000 characters'], Response::HTTP_BAD_REQUEST);
        }

        $message->setContent(trim($newContent));
        $this->em->flush();

        // Mapowanie na DTO
        $dto = new MessageDTO();
        $dto->id = $message->getId();
        $dto->userId = $message->getUser()->getId();
        $dto->username = $message->getUser()->getUsername();
        $dto->userAvatar = $message->getUser()->getAvatar();
        $dto->groupId = $message->getGroup()->getId();
        $dto->content = $message->getContent();
        $dto->createdAt = $message->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $message->getUpdatedAt()->format('Y-m-d H:i:s');

        return $this->json($dto, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->security->getUser();
        $message = $this->messageRepo->find($id);

        if (!$message) {
            return $this->json(['error' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie ownership
        if ($message->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'You can only delete your own messages'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($message);
        $this->em->flush();

        return $this->json(['success' => true], Response::HTTP_OK);
    }
}
