<?php
declare(strict_types=1);
namespace App\Service;
use App\DTO\API\V1\User\UpdateUserInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final readonly class UserManagementService
{
    public function __construct(private EntityManagerInterface $entityManager, private UserRepository $repository, private UserPasswordHasherInterface $passwordHasher, private Security $security) {}
    public function update(User $target, User $actor, UpdateUserInput $input): User
    {
        if ($input->username !== null) { $value=trim($input->username);$this->assertUnique('username',$value,$target);$target->setUsername($value); }
        if ($input->email !== null) { $value=trim($input->email);$this->assertUnique('email',$value,$target);$target->setEmail($value); }
        if ($input->password !== null) { $target->setPassword($this->passwordHasher->hashPassword($target,$input->password)); }
        if ($input->isVerified !== null) { $target->setIsVerified($input->isVerified); }
        if ($input->roles !== null) { $this->updateRoles($target,$actor,$input->roles); }
        $this->entityManager->flush();
        return $target;
    }
    /** @param list<string> $roles */
    private function updateRoles(User $target, User $actor, array $roles): void
    {
        if ($target->getId() === $actor->getId()) { throw new AccessDeniedHttpException('Administrators cannot change their own roles.'); }
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && array_intersect(['ROLE_ADMIN','ROLE_SUPER_ADMIN'],$roles) !== []) { throw new AccessDeniedHttpException('Only a super administrator can assign administrative roles.'); }
        $target->setRoles(array_values(array_unique($roles)));
    }
    private function assertUnique(string $field, string $value, User $current): void
    {
        $existing=$this->repository->findOneBy([$field=>$value]);
        if ($existing !== null && $existing !== $current) { throw new ConflictHttpException("A user with this {$field} already exists."); }
    }
}
