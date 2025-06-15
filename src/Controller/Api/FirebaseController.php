<?php

namespace App\Controller\Api;

use App\Entity\DeviceToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FirebaseController extends AbstractController
{
    #[Route('/api/save-token', name: 'api_save_token', methods: ['POST'])]
    public function saveToken(Request $request, EntityManagerInterface $em): JsonResponse
    {
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


            $em->persist($deviceToken);
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }
}