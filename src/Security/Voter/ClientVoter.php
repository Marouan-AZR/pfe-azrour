<?php

namespace App\Security\Voter;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClientVoter extends Voter
{
    public const CREATE = 'CLIENT_CREATE';
    public const VIEW = 'CLIENT_VIEW';
    public const EDIT = 'CLIENT_EDIT';
    public const DELETE = 'CLIENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATE, self::VIEW, self::EDIT, self::DELETE])
            && ($subject instanceof Client || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $this->canManage($user),
            self::VIEW => $this->canView($user),
            default => false,
        };
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value);
    }

    private function canView(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value) 
            || $user->hasRole(UserRole::CONTROLEUR->value)
            || $user->hasRole(UserRole::DIRECTEUR->value)
            || $user->hasRole(UserRole::PATRON->value);
    }
}
