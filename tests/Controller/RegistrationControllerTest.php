<?php

namespace App\Tests\Controller;

use App\Controller\RegistrationController;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationControllerTest extends TestCase
{
    public function testRegisterPersistsUserAndLogsIn(): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $controller = $this->getMockBuilder(RegistrationController::class)
            ->setConstructorArgs([$emailVerifier])
            ->onlyMethods(['createForm'])
            ->getMock();

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest')->with($this->isInstanceOf(Request::class));
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $plainPasswordChild = $this->createMock(FormInterface::class);
        $plainPasswordChild->method('getData')->willReturn('plain');
        $form->method('get')->with('plainPassword')->willReturn($plainPasswordChild);

        $controller->expects($this->once())
            ->method('createForm')
            ->with(RegistrationFormType::class, $this->isInstanceOf(User::class))
            ->willReturnCallback(function (string $type, User $user) use ($form) {
                $user->setEmail('user@test.fr');
                return $form;
            });

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), 'plain')
            ->willReturn('hashed');

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (User $user) {
                return $user->getPassword() === 'hashed';
            }));
        $entityManager->expects($this->once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('login')
            ->with($this->isInstanceOf(User::class), 'form_login', 'main')
            ->willReturn(new Response('logged'));

        $emailVerifier->expects($this->once())
            ->method('sendEmailConfirmation')
            ->with(
                'app_verify_email',
                $this->isInstanceOf(User::class),
                $this->callback(fn (TemplatedEmail $email) => $email->getFrom()[0]->getAddress() === 'support@lacouisine.fr')
            );

        $response = $controller->register(new Request(), $hasher, $security, $entityManager);
        $this->assertSame('logged', $response->getContent());
    }

    public function testRegisterRendersWhenFormInvalid(): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $controller = $this->getMockBuilder(RegistrationController::class)
            ->setConstructorArgs([$emailVerifier])
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $controller->expects($this->once())->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('render')
            ->with('registration/register.html.twig', ['registrationForm' => $form])
            ->willReturn(new Response('render'));

        $response = $controller->register(
            new Request(),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(Security::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class)
        );

        $this->assertSame('render', $response->getContent());
    }

    public function testVerifyUserEmailSuccess(): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $controller = $this->getMockBuilder(RegistrationController::class)
            ->setConstructorArgs([$emailVerifier])
            ->onlyMethods(['denyAccessUnlessGranted', 'getUser', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $user = new User();
        $controller->expects($this->once())->method('denyAccessUnlessGranted')->with('IS_AUTHENTICATED_FULLY');
        $controller->expects($this->once())->method('getUser')->willReturn($user);

        $emailVerifier->expects($this->once())->method('handleEmailConfirmation')->with($this->isInstanceOf(Request::class), $user);

        $controller->expects($this->once())->method('addFlash')->with('success', 'Your email address has been verified.');
        $controller->expects($this->once())->method('redirectToRoute')->with('app_register')
            ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/register'));

        $response = $controller->verifyUserEmail(new Request(), $this->createMock(TranslatorInterface::class));
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testVerifyUserEmailHandlesException(): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $controller = $this->getMockBuilder(RegistrationController::class)
            ->setConstructorArgs([$emailVerifier])
            ->onlyMethods(['denyAccessUnlessGranted', 'getUser', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $user = new User();
        $controller->expects($this->once())->method('denyAccessUnlessGranted');
        $controller->expects($this->once())->method('getUser')->willReturn($user);

        $emailVerifier->method('handleEmailConfirmation')->willThrowException(
            new class('error') extends \Exception implements VerifyEmailExceptionInterface {
                public function getReason(): string { return $this->getMessage(); }
            }
        );

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('error', [], 'VerifyEmailBundle')->willReturn('translated');

        $controller->expects($this->once())->method('addFlash')->with('verify_email_error', 'translated');
        $controller->expects($this->once())->method('redirectToRoute')->with('app_register')
            ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/register'));

        $response = $controller->verifyUserEmail(new Request(), $translator);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }
}

