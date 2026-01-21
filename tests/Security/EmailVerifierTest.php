<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifierTest extends TestCase
{
    public function testSendEmailConfirmationAddsContextAndSends(): void
    {
        $verifyHelper = new class implements VerifyEmailHelperInterface {
            public array $validateArgs = [];
            public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
            {
                $generatedAt = time();
                $expiresAt = (new \DateTimeImmutable())->setTimestamp($generatedAt + 3600);
                return new VerifyEmailSignatureComponents($expiresAt, 'signed', $generatedAt);
            }
            public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
            {
                // not used in our EmailVerifier
            }
            public function __call(string $name, array $arguments): mixed
            {
                if ($name === 'validateEmailConfirmationFromRequest') {
                    $this->validateArgs = $arguments;
                    return null;
                }
                throw new \BadMethodCallException($name);
            }
        };
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('u@test.fr');
        $idProp = new \ReflectionProperty(User::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($user, 10);

        // generateSignature is provided by the fake helper above (real VerifyEmailSignatureComponents).

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $ctx = $email->getContext();
                return $ctx['signedUrl'] === 'signed'
                    && is_string($ctx['expiresAtMessageKey'])
                    && is_array($ctx['expiresAtMessageData']);
            }));

        $verifier = new EmailVerifier($verifyHelper, $mailer, $em);
        $email = (new TemplatedEmail())->context(['x' => 'y']);
        $verifier->sendEmailConfirmation('route', $user, $email);
    }

    public function testHandleEmailConfirmationValidatesAndPersists(): void
    {
        $verifyHelper = new class implements VerifyEmailHelperInterface {
            public array $validateArgs = [];
            public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
            {
                $generatedAt = time();
                $expiresAt = (new \DateTimeImmutable())->setTimestamp($generatedAt + 3600);
                return new VerifyEmailSignatureComponents($expiresAt, 'signed', $generatedAt);
            }
            public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
            {
                // not used
            }
            public function __call(string $name, array $arguments): mixed
            {
                if ($name === 'validateEmailConfirmationFromRequest') {
                    $this->validateArgs = $arguments;
                    return null;
                }
                throw new \BadMethodCallException($name);
            }
        };
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('u@test.fr');
        $idProp = new \ReflectionProperty(User::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($user, 10);

        $request = new Request();

        // validateEmailConfirmationFromRequest is handled via __call on the fake helper

        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $verifier = new EmailVerifier($verifyHelper, $mailer, $em);
        $this->assertFalse($user->isVerified());
        $verifier->handleEmailConfirmation($request, $user);
        $this->assertTrue($user->isVerified());

        $this->assertSame([$request, '10', 'u@test.fr'], $verifyHelper->validateArgs);
    }
}


