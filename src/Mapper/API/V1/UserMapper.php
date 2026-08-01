<?php
declare(strict_types=1);
namespace App\Mapper\API\V1;
use App\DTO\API\V1\User\UserDetails;
use App\DTO\API\V1\User\UserListItem;
use App\Entity\User;
final class UserMapper
{
    public function toListItem(User $user): UserListItem
    {
        return new UserListItem((int) $user->getId(), (string) $user->getUsername(), array_values($user->getRoles()), $user->isVerified());
    }
    public function toDetails(User $user): UserDetails
    {
        return new UserDetails((int) $user->getId(), (string) $user->getUsername(), (string) $user->getEmail(), array_values($user->getRoles()), $user->isVerified(), $user->getRecipes()->count());
    }
}
