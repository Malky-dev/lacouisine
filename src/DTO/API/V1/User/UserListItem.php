<?php
declare(strict_types=1);
namespace App\DTO\API\V1\User;
final readonly class UserListItem
{
    /** @param list<string> $roles */
    public function __construct(public int $id, public string $username, public array $roles, public bool $isVerified) {}
}
