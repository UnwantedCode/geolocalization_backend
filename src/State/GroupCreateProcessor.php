<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Group;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Custom processor for creating groups with owner and automatic membership
 */
class GroupCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ProcessorInterface $persistProcessor
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Only handle POST (create) - detect by absence of previous_data
        if (!$data instanceof Group || ($context['previous_data'] ?? null)) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Get authenticated user
        $user = $this->security->getUser();
        if (!$user) {
            throw new \LogicException('User must be authenticated to create a group');
        }

        // 1. Set owner to current user
        $data->setOwner($user);

        // 2. Add current user as member
        $data->addUser($user);

        // 3. Persist via default processor
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
