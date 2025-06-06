<?php

namespace App\Controller\Api;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
class LoginController extends AbstractController
{
    #[Route('/api/login', name: 'login', methods: ['POST'])]
    public function login(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if ($user) {
            return new JsonResponse([
                'email' => $user->getEmail(),
                'roles' => $user->getRoles()
            ]);
        }
        return new JsonResponse(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
    }


}
