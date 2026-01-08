<?php

namespace App\Security;

use App\Entity\Group;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GroupVoter extends Voter
{
    public const EDIT = 'GROUP_EDIT';
    public const DELETE = 'GROUP_DELETE';
    public const VIEW = 'GROUP_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::VIEW])
            && $subject instanceof Group;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof User) {
            return false;
        }

        /** @var Group $group */
        $group = $subject;

        return match ($attribute) {
            self::EDIT, self::DELETE => $this->isOwner($user, $group),
            self::VIEW => $this->isMember($user, $group),
            default => false,
        };
    }

    private function isOwner(User $user, Group $group): bool
    {
        return $group->getOwner() === $user;
    }

    private function isMember(User $user, Group $group): bool
    {
        return $group->getUsers()->contains($user);
    }
}
