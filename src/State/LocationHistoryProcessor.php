<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\LocationHistory;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LocationHistoryProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProcessorInterface $persistProcessor
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof LocationHistory) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Only check on create (POST)
        if ($context['previous_data'] ?? null) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User must be authenticated');
        }

        // Check privacy mode
        if ($user->isPrivacyMode()) {
            throw new AccessDeniedHttpException('Location tracking is disabled in privacy mode');
        }

        // Set user if not already set
        if ($data->getUser() === null) {
            $data->setUser($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}