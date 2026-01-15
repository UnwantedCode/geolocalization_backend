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
            $dto->id = $user->getId();
            $dto->email = $user->getEmail();
            $dto->username = $user->getUsername();
            $dto->avatar = $user->getAvatar();
            $dto->privacyMode = $user->isPrivacyMode();

            foreach ($user->getGroups() as $group) {
                $groupDto = new GroupDTO();
                $groupDto->id = $group->getId();
                $groupDto->name = $group->getName();
                $groupDto->code = $group->getCode();
                $dto->groups[] = $groupDto;
            }

            $allLocations = $user->getLocationHistories()->toArray();
            usort($allLocations, fn($a, $b) => $b->getId() <=> $a->getId());

            // Filter locations from last 7 days
            $sevenDaysAgo = new \DateTime('-7 days');
            $locationsLast7Days = array_filter($allLocations, function($loc) use ($sevenDaysAgo) {
                return $loc->getCreatedAt() >= $sevenDaysAgo;
            });

            // If no locations in last 7 days, take last 10 locations
            if (empty($locationsLast7Days)) {
                $locations = array_slice($allLocations, 0, 10);
            } else {
                $locations = array_values($locationsLast7Days);
            }

            // first location is the most recent
            $mostRecentLocation = $allLocations[0] ?? null;
            if ($mostRecentLocation) {
                $locDto = new LocationDTO();
                $locDto->id = $mostRecentLocation->getId();
                $locDto->latitude = $mostRecentLocation->getLatitude();
                $locDto->longitude = $mostRecentLocation->getLongitude();
                $locDto->timestamp = $mostRecentLocation->getCreatedAt()->format('Y-m-d H:i:s');
                $locDto->batteryLevel = $mostRecentLocation->getBatteryLevel();
                $dto->locationCurrent = $locDto;
            }

            foreach ($locations as $location) {
                $locDto = new LocationDTO();
                $locDto->id = $location->getId();
                $locDto->latitude = $location->getLatitude();
                $locDto->longitude = $location->getLongitude();
                $locDto->timestamp = $location->getCreatedAt()->format('Y-m-d H:i:s');
                $locDto->batteryLevel = $location->getBatteryLevel();
                $dto->locationHistories[] = $locDto;
            }

            $output[] = $dto;
        }

        return $this->json($output);
    }
}
