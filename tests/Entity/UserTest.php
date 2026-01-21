<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testGettersSettersRolesAndSerialize(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
        $this->assertNull($user->getUsername());
        $this->assertSame('ROLE_USER', $user->getRoles()[0]);
        $this->assertNull($user->getPassword());
        $this->assertNull($user->getEmail());
        $this->assertFalse($user->isVerified());
        $this->assertNull($user->getApiToken());

        $user
            ->setUsername('bob')
            ->setEmail('bob@test.fr')
            ->setPassword('hashed')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
            ->setIsVerified(true)
            ->setApiToken('t');

        $this->assertSame('bob', $user->getUsername());
        $this->assertSame('bob', $user->getUserIdentifier());
        $this->assertSame('bob@test.fr', $user->getEmail());
        $this->assertSame('hashed', $user->getPassword());
        $this->assertTrue($user->isVerified());
        $this->assertSame('t', $user->getApiToken());

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);

        $serialized = $user->__serialize();
        $passwordKey = "\0".User::class."\0password";
        $this->assertArrayHasKey($passwordKey, $serialized);
        $this->assertSame(hash('crc32c', 'hashed'), $serialized[$passwordKey]);

        // deprecated method still callable
        $user->eraseCredentials();
        $this->assertTrue(true);
    }
}




