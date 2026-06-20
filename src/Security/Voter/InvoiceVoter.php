<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class InvoiceVoter extends Voter
{
    public const CREATE = 'INVOICE_CREATE';
    public const VIEW = 'INVOICE_VIEW';
    public const VALIDATE = 'INVOICE_VALIDATE';
    public const EXPORT = 'INVOICE_EXPORT';
    public const DELETE = 'INVOICE_DELETE';
    public const SEND = 'INVOICE_SEND';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::CREATE, self::VIEW, self::VALIDATE, self::EXPORT, self::DELETE, self::SEND])
            && ($subject instanceof Invoice || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE   => $this->canCreate($user),
            self::VIEW     => $this->canView($user),
            self::VALIDATE => $this->canValidate($user),
            self::EXPORT   => $this->canExport($user),
            self::DELETE   => $this->canDelete($user),
            self::SEND     => $this->canSend($user),
            default        => false,
        };
    }

    private function canCreate(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value);
    }

    private function canView(User $user): bool
    {
        return ($user->hasRole(UserRole::CHEF_STOCK->value)
            || $user->hasRole(UserRole::DIRECTEUR->value))
            && !$user->hasRole(UserRole::PATRON->value);
    }

    private function canSend(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value);
    }

    private function canValidate(User $user): bool
    {
        return $user->hasRole(UserRole::DIRECTEUR->value);
    }

    private function canDelete(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value)
            || $user->hasRole(UserRole::DIRECTEUR->value);
    }

    private function canExport(User $user): bool
    {
        return ($user->hasRole(UserRole::CHEF_STOCK->value)
            || $user->hasRole(UserRole::DIRECTEUR->value))
            && !$user->hasRole(UserRole::PATRON->value);
    }
}
