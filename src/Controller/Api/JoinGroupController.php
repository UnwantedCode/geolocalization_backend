<?php

namespace App\Controller\Api;

use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user/join-group', name: 'api_user_join_group', methods: ['POST'])]
class JoinGroupController extends AbstractController
{
    public function __invoke(
        Request $request,
        GroupRepository $groupRepo,
        Security $security,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $security->getUser();
        $data = json_decode($request->getContent(), true);
        $groupCode = $data['code'] ?? null;

        if (!$groupCode) {
            return $this->json(['error' => 'Group code is required'], Response::HTTP_BAD_REQUEST);
        }
        $group = $groupRepo->findOneBy(['code' => $groupCode]);
        if (!$group) {
            return $this->json(['error' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }
        if ($group->getUsers()->contains($user)) {
            return $this->json(['error' => 'User is already a member of this group'], Response::HTTP_CONFLICT);
        }


        $group->addUser($user);
        $entityManager->persist($group);
        $entityManager->flush();
        return $this->json(['message' => 'User added to group successfully'], Response::HTTP_CREATED);
    }
}