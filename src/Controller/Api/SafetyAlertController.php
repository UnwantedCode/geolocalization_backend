<?php

namespace App\Controller\Api;

use App\Dto\SafetyAlertDTO;
use App\Entity\SafetyAlert;
use App\Entity\LocationHistory;
use App\Entity\Message;
use App\Repository\GroupRepository;
use App\Repository\SafetyAlertRepository;
use App\Service\SafetyAlertNotificationService;
use App\Service\MessageNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/safety-alerts', name: 'api_safety_alerts_')]
class SafetyAlertController extends AbstractController
{
    public function __construct(
        private SafetyAlertRepository $alertRepo,
        private GroupRepository $groupRepo,
        private EntityManagerInterface $em,
        private Security $security,
        private SafetyAlertNotificationService $alertNotificationService,
        private MessageNotificationService $messageNotificationService
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $data = json_decode($request->getContent(), true);

        // Walidacja wymaganych pól
        $groupId = $data['group_id'] ?? null;
        $type = $data['type'] ?? null;
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if (!$groupId) {
            return $this->json(['error' => 'group_id is required'], Response::HTTP_BAD_REQUEST);
        }

        if (!$type || trim($type) === '') {
            return $this->json(['error' => 'type is required and cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        if ($latitude === null || $longitude === null) {
            return $this->json(['error' => 'latitude and longitude are required'], Response::HTTP_BAD_REQUEST);
        }

        // Walidacja zakresu coordinates
        if ($latitude < -90 || $latitude > 90) {
            return $this->json(['error' => 'latitude must be between -90 and 90'], Response::HTTP_BAD_REQUEST);
        }

        if ($longitude < -180 || $longitude > 180) {
            return $this->json(['error' => 'longitude must be between -180 and 180'], Response::HTTP_BAD_REQUEST);
        }

        // Walidacja battery level (opcjonalny)
        $batteryLevel = $data['batteryLevel'] ?? null;
        if ($batteryLevel !== null && ($batteryLevel < 0 || $batteryLevel > 100)) {
            return $this->json(['error' => 'batteryLevel must be between 0 and 100'], Response::HTTP_BAD_REQUEST);
        }

        // Walidacja message (opcjonalny)
        $message = $data['message'] ?? null;
        if ($message !== null && mb_strlen($message) > 500) {
            return $this->json(['error' => 'message cannot exceed 500 characters'], Response::HTTP_BAD_REQUEST);
        }

        // Sprawdzenie czy grupa istnieje
        $group = $this->groupRepo->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie członkostwa
        if (!$group->getUsers()->contains($user)) {
            return $this->json(['error' => 'You are not a member of this group'], Response::HTTP_FORBIDDEN);
        }

        // Utworzenie wpisu lokalizacji
        $locationHistory = new LocationHistory();
        $locationHistory->setUser($user);
        $locationHistory->setLatitude((float) $latitude);
        $locationHistory->setLongitude((float) $longitude);

        if ($batteryLevel !== null) {
            $locationHistory->setBatteryLevel((int) $batteryLevel);
        } else {
            $locationHistory->setBatteryLevel(0); // Default value jeśli brak
        }

        $this->em->persist($locationHistory);

        // Utworzenie alertu
        $alert = new SafetyAlert();
        $alert->setUser($user);
        $alert->setGroup($group);
        $alert->setType(trim($type));
        $alert->setLocation($locationHistory);

        if ($message !== null && trim($message) !== '') {
            $alert->setMessage(trim($message));
        }

        $this->em->persist($alert);

        // Automatyczne utworzenie wiadomości w chacie grupy
        $chatMessage = new Message();
        $chatMessage->setUser($user);
        $chatMessage->setGroup($group);

        $messageContent = sprintf(
            "SOS Alert od %s współrzędne (%.6f, %.6f)",
            $user->getUsername(),
            $latitude,
            $longitude
        );

        if ($message) {
            $messageContent .= "\nWiadomość: " . $message;
        }

        if ($batteryLevel !== null) {
            $messageContent .= sprintf("\nBateria: %d%%", $batteryLevel);
        }

        $chatMessage->setContent($messageContent);
        $this->em->persist($chatMessage);

        $this->em->flush();

        // Wysłanie notyfikacji (asynchroniczne - nie czekamy na wynik)
        try {
            // Push notification dla alertu
            $this->alertNotificationService->sendAlertNotification($alert, $group, $user);

            // Push notification dla wiadomości w chacie
            $this->messageNotificationService->sendGroupMessageNotification($group, $user, $messageContent);
        } catch (\Throwable $e) {
            // Logowanie błędu, ale nie przerywamy zwracania odpowiedzi
        }

        // Mapowanie na DTO
        $dto = $this->mapToDTO($alert);

        return $this->json($dto, Response::HTTP_CREATED);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $groupId = $request->query->get('group_id');
        $activeParam = $request->query->get('active');

        // Walidacja group_id (required)
        if (!$groupId) {
            return $this->json(['error' => 'group_id is required'], Response::HTTP_BAD_REQUEST);
        }

        // Sprawdzenie czy grupa istnieje
        $group = $this->groupRepo->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie członkostwa
        if (!$group->getUsers()->contains($user)) {
            return $this->json(['error' => 'You are not a member of this group'], Response::HTTP_FORBIDDEN);
        }

        // Parsowanie active parameter
        $activeOnly = null;
        if ($activeParam !== null) {
            $activeOnly = filter_var($activeParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activeOnly === null) {
                return $this->json(['error' => 'active parameter must be "true" or "false"'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Pobranie alertów
        $alerts = $this->alertRepo->findGroupAlerts((int) $groupId, $activeOnly);

        // Mapowanie na DTO
        $output = array_map(fn($alert) => $this->mapToDTO($alert), $alerts);

        return $this->json($output, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $alert = $this->alertRepo->find($id);

        if (!$alert) {
            return $this->json(['error' => 'Safety alert not found'], Response::HTTP_NOT_FOUND);
        }

        // Sprawdzenie ownership - TYLKO autor może zamknąć alert
        if ($alert->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'You can only resolve your own alerts'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Walidacja - tylko resolved może być zmienione
        if (!isset($data['resolved'])) {
            return $this->json(['error' => 'resolved field is required'], Response::HTTP_BAD_REQUEST);
        }

        $resolved = filter_var($data['resolved'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($resolved === null) {
            return $this->json(['error' => 'resolved must be a boolean'], Response::HTTP_BAD_REQUEST);
        }

        // Aktualizacja statusu
        $alert->setResolved($resolved);

        if ($resolved) {
            $alert->setResolvedAt(new \DateTime());
        } else {
            // Jeśli ktoś ponownie otwiera alert (edge case)
            $alert->setResolvedAt(null);
        }

        $this->em->flush();

        // Mapowanie na DTO
        $dto = $this->mapToDTO($alert);

        return $this->json($dto, Response::HTTP_OK);
    }

    /**
     * Helper method to map SafetyAlert entity to DTO
     */
    private function mapToDTO(SafetyAlert $alert): SafetyAlertDTO
    {
        $dto = new SafetyAlertDTO();
        $dto->id = $alert->getId();
        $dto->userId = $alert->getUser()->getId();
        $dto->username = $alert->getUser()->getUsername();
        $dto->userAvatar = $alert->getUser()->getAvatar();
        $dto->groupId = $alert->getGroup()->getId();
        $dto->groupName = $alert->getGroup()->getName();
        $dto->type = $alert->getType();
        $dto->message = $alert->getMessage();
        $dto->latitude = $alert->getLocation()->getLatitude();
        $dto->longitude = $alert->getLocation()->getLongitude();
        $dto->batteryLevel = $alert->getLocation()->getBatteryLevel();
        $dto->resolved = $alert->getResolved();
        $dto->resolvedAt = $alert->getResolvedAt()?->format('Y-m-d H:i:s');
        $dto->createdAt = $alert->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $alert->getUpdatedAt()->format('Y-m-d H:i:s');

        return $dto;
    }
}
