<?php

namespace App\Controller\Api;

use App\Dto\GroupDTO;
use App\Dto\LocationDTO;
use App\Dto\UserDTO;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user-data', name: 'api_user_data', methods: ['GET'])]
class UserGroupLocationController extends AbstractController
{
    #[Route('', name: 'get_user_group_data')]
    public function __invoke(UserRepository $userRepo, Security $security): JsonResponse
    {
        $currentUser = $security->getUser();
        $users = $userRepo->findGroupUsersWithLocations($currentUser);

        $output = [];

        foreach ($users as $user) {
            $dto = new UserDTO();
            $dto->email = $user->getEmail();
            $dto->username = $user->getUsername();
            $dto->avatar = $user->getAvatar();

            // Grupy
            foreach ($user->getGroups() as $group) {
                $groupDto = new GroupDTO();
                $groupDto->id = $group->getId();
                $groupDto->name = $group->getName();
                $dto->groups[] = $groupDto;
            }

            // Mapuj lokalizacje
            foreach ($user->getLocationHistories() as $location) {
                $locDto = new LocationDTO();
                $locDto->latitude = $location->getLatitude();
                $locDto->longitude = $location->getLongitude();
                $locDto->timestamp = $location->getCreatedAt()->format('Y-m-d H:i:s'); // lub inne formatowanie

                $dto->locationHistories[] = $locDto;
            }

            $output[] = $dto;
        }

        return $this->json($output);
    }
}
