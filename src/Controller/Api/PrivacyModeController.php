<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PrivacyModeController extends AbstractController
{
    #[Route('/api/user/privacy-mode', name: 'api_user_privacy_mode', methods: ['POST'])]
    public function toggle(
        Request $request,
        Security $security,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $enabled = $data['enabled'] ?? null;

        if ($enabled === null) {
            return $this->json(['error' => 'enabled field is required'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPrivacyMode((bool) $enabled);
        $entityManager->flush();

        return $this->json([
            'message' => $enabled ? 'Privacy mode enabled' : 'Privacy mode disabled',
            'privacyMode' => $user->isPrivacyMode()
        ], Response::HTTP_OK);
    }

    #[Route('/api/user/privacy-mode', name: 'api_user_privacy_mode_get', methods: ['GET'])]
    public function getStatus(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'privacyMode' => $user->isPrivacyMode()
        ], Response::HTTP_OK);
    }
}