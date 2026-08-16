<?php
declare(strict_types=1);
namespace App\Security\Voter;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
final class UserVoter extends Voter
{
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';
    public function __construct(private readonly Security $security) {}
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof User && in_array($attribute, [self::EDIT, self::DELETE], true);
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User || !$this->security->isGranted('ROLE_ADMIN')) { return false; }
        /** @var User $target */ $target = $subject;
        if ($actor->getId() === $target->getId()) { return true; }
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) { return true; }
        return array_intersect(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $target->getRoles()) === [];
    }
}
