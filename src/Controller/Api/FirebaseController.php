<?php

namespace App\Controller\Api;

use App\Entity\DeviceToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FirebaseController extends AbstractController
{
    #[Route('/api/save-token', name: 'api_save_token', methods: ['POST'])]
    public function saveToken(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        $data = json_decode($request->getContent(), true);
        $token = $data['fcmToken'] ?? null;

        if (!$token) {
            return new JsonResponse(['error' => 'Brak tokenu'], 400);
        }

        // unikaj duplikatów
        $existing = $em->getRepository(DeviceToken::class)->findOneBy(['token' => $token]);
        if (!$existing) {
            $deviceToken = new DeviceToken();
            $deviceToken->setToken($token);
            $deviceToken->setUser($user);

            $em->persist($deviceToken);
            $em->flush();
        } elseif ($existing->getUser() === null && $user) {
            // Update legacy token z userem
            $existing->setUser($user);
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }
}