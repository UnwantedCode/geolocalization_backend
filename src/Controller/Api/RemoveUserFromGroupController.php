<?php

namespace App\Controller\Api;

use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/group/remove-user', name: 'api_group_remove_user', methods: ['POST'])]
class RemoveUserFromGroupController extends AbstractController
{
    public function __invoke(
        Request $request,
        GroupRepository $groupRepo,
        UserRepository $userRepo,
        Security $security,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $currentUser = $security->getUser();
        $data = json_decode($request->getContent(), true);

        $groupId = $data['groupId'] ?? null;
        $userId = $data['userId'] ?? null;

        if (!$groupId || !$userId) {
            return $this->json(['error' => 'groupId and userId are required'], Response::HTTP_BAD_REQUEST);
        }

        $group = $groupRepo->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Group not found'], Response::HTTP_NOT_FOUND);
        }

        if ($group->getOwner() !== $currentUser) {
            return $this->json(['error' => 'Only the group owner can remove users'], Response::HTTP_FORBIDDEN);
        }

        $userToRemove = $userRepo->find($userId);
        if (!$userToRemove) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$group->getUsers()->contains($userToRemove)) {
            return $this->json(['error' => 'User is not a member of this group'], Response::HTTP_BAD_REQUEST);
        }

        if ($userToRemove === $currentUser) {
            return $this->json(['error' => 'Owner cannot remove themselves from the group'], Response::HTTP_BAD_REQUEST);
        }

        $group->removeUser($userToRemove);
        $entityManager->flush();

        return $this->json(['message' => 'User removed from group successfully'], Response::HTTP_OK);
    }
}