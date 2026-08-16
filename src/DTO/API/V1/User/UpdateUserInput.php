<?php
declare(strict_types=1);
namespace App\DTO\API\V1\User;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class UpdateUserInput
{
    /** @param list<string>|null $roles */
    public function __construct(
        #[Assert\Length(min: 3, max: 180)] public ?string $username = null,
        #[Assert\Email] #[Assert\Length(max: 255)] public ?string $email = null,
        #[Assert\Length(min: 6, max: 4096)] public ?string $password = null,
        #[Assert\All([new Assert\Choice(['ROLE_USER', 'ROLE_CREATOR', 'ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'])])]
        #[Assert\Count(min: 1)] public ?array $roles = null,
        public ?bool $isVerified = null,
    ) {}
}
