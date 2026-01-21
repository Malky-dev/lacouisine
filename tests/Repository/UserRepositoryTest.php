<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserRepositoryTest extends TestCase
{
    public function testUpgradePasswordThrowsForUnsupportedUser(): void
    {
        $repo = $this->getMockBuilder(UserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();

        $unsupported = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string { return 'x'; }
        };

        $this->expectException(UnsupportedUserException::class);
        $repo->upgradePassword($unsupported, 'new');
    }

    public function testUpgradePasswordPersistsAndFlushes(): void
    {
        $repo = $this->getMockBuilder(UserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $em->expects($this->once())->method('flush');

        $repo->method('getEntityManager')->willReturn($em);

        $user = new User();
        $repo->upgradePassword($user, 'hashed2');

        $this->assertSame('hashed2', $user->getPassword());
    }
}




