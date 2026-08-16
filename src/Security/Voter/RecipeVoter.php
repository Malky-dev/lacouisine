<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeVisibility;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RecipeVoter extends Voter
{
    public const VIEW = 'RECIPE_VIEW';
    public const EDIT = 'RECIPE_EDIT';
    public const DELETE = 'RECIPE_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Recipe
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
    ): bool {
        /** @var Recipe $recipe */
        $recipe = $subject;

        if ($attribute === self::VIEW && $recipe->getVisibility() === RecipeVisibility::PUBLIC) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $isOwner = $recipe->getCreatedBy()?->getId() !== null
            && $recipe->getCreatedBy()?->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $isOwner,
            self::EDIT, self::DELETE => $isOwner
                && $this->security->isGranted('ROLE_CREATOR'),
            default => false,
        };
    }
}
