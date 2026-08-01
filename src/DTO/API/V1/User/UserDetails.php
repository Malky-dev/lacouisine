<?php
declare(strict_types=1);
namespace App\DTO\API\V1\User;
final readonly class UserDetails
{
    /** @param list<string> $roles */
    public function __construct(public int $id, public string $username, public string $email, public array $roles, public bool $isVerified, public int $recipeCount) {}
}
