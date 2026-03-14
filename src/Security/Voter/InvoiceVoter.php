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

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATE, self::VIEW, self::VALIDATE, self::EXPORT])
            && ($subject instanceof Invoice || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($user),
            self::VIEW => $this->canView($user, $subject),
            self::VALIDATE => $this->canValidate($user),
            self::EXPORT => $this->canExport($user),
            default => false,
        };
    }

    private function canCreate(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value);
    }

    private function canView(User $user, ?Invoice $invoice): bool
    {
        if ($user->hasRole(UserRole::CHEF_STOCK->value) 
            || $user->hasRole(UserRole::DIRECTEUR->value)
            || $user->hasRole(UserRole::PATRON->value)) {
            return true;
        }

        // Client can only view their own invoices
        if ($user->hasRole(UserRole::CLIENT->value) && $invoice !== null) {
            return $user->getClient() === $invoice->getClient();
        }

        return false;
    }

    private function canValidate(User $user): bool
    {
        return $user->hasRole(UserRole::DIRECTEUR->value);
    }

    private function canExport(User $user): bool
    {
        return $user->hasRole(UserRole::CHEF_STOCK->value) 
            || $user->hasRole(UserRole::DIRECTEUR->value)
            || $user->hasRole(UserRole::PATRON->value);
    }
}
