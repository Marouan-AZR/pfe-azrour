<?php

namespace App\Security\Voter;

use App\Entity\StockEntry;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class StockEntryVoter extends Voter
{
    public const CREATE = 'STOCK_ENTRY_CREATE';
    public const VIEW = 'STOCK_ENTRY_VIEW';
    public const VALIDATE = 'STOCK_ENTRY_VALIDATE';
    public const REJECT = 'STOCK_ENTRY_REJECT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATE, self::VIEW, self::VALIDATE, self::REJECT])
            && ($subject instanceof StockEntry || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($user),
            self::VIEW => $this->canView($user),
            self::VALIDATE, self::REJECT => $this->canValidate($user),
            default => false,
        };
    }

    private function canCreate(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value) 
            || $user->hasRole(UserRole::CONTROLEUR->value)
            || $user->hasRole(UserRole::DIRECTEUR->value)
            || $user->hasRole(UserRole::PATRON->value);
    }

    private function canView(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value) 
            || $user->hasRole(UserRole::CONTROLEUR->value)
            || $user->hasRole(UserRole::DIRECTEUR->value)
            || $user->hasRole(UserRole::PATRON->value);
    }

    private function canValidate(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value);
    }
}
