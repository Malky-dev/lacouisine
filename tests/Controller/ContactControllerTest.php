<?php

namespace App\Tests\Controller;

use App\Controller\ContactController;
use App\DTO\ContactDTO;
use App\Form\ContactType;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;

final class ContactControllerTest extends TestCase
{
    public function testContactSendsEmailAndRedirectsOnValidForm(): void
    {
        $controller = $this->getMockBuilder(ContactController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $mailer = $this->createMock(MailerInterface::class);

        $data = new ContactDTO();
        $data->service = 'support';
        $data->email = 'user@test.fr';
        $data->object = 'Hello';
        $data->message = 'Test';
        $data->name = 'User';

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest')->with($this->isInstanceOf(Request::class));
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

        $controller->expects($this->once())
            ->method('createForm')
            ->with(ContactType::class, $this->isInstanceOf(ContactDTO::class))
            ->willReturnCallback(function (string $type, ContactDTO $dto) use ($form) {
                $dto->service = 'support';
                $dto->email = 'user@test.fr';
                $dto->object = 'Hello';
                $dto->message = 'Test';
                $dto->name = 'User';
                return $form;
            });

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(TemplatedEmail::class));

        $controller->expects($this->once())->method('addFlash')->with('success', 'Votre email a bien été envoyé');
        $controller->expects($this->once())->method('redirectToRoute')->with('contact')
            ->willReturn(new RedirectResponse('/contact'));

        $response = $controller->contact(new Request(), $mailer);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testContactAddsDangerFlashOnMailerException(): void
    {
        $controller = $this->getMockBuilder(ContactController::class)
            ->onlyMethods(['createForm', 'addFlash', 'render'])
            ->getMock();

        $mailer = $this->createMock(MailerInterface::class);
        $data = new ContactDTO();
        $data->service = 'support';
        $data->email = 'user@test.fr';
        $data->object = 'Hello';
        $data->message = 'Test';
        $data->name = 'User';

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

        $controller->expects($this->once())
            ->method('createForm')
            ->with(ContactType::class, $this->isInstanceOf(ContactDTO::class))
            ->willReturnCallback(function (string $type, ContactDTO $dto) use ($form) {
                $dto->service = 'support';
                $dto->email = 'user@test.fr';
                $dto->object = 'Hello';
                $dto->message = 'Test';
                $dto->name = 'User';
                return $form;
            });
        $mailer->method('send')->willThrowException(new Exception('fail'));

        $controller->expects($this->once())->method('addFlash')->with('danger', "Impossible d'envoyer votre email");
        $controller->expects($this->once())->method('render')
            ->with('contact/contact.html.twig', ['form' => $form])
            ->willReturn(new Response('render'));

        $response = $controller->contact(new Request(), $mailer);
        $this->assertSame('render', $response->getContent());
    }

    public function testContactRendersFormWhenNotSubmitted(): void
    {
        $controller = $this->getMockBuilder(ContactController::class)
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $mailer = $this->createMock(MailerInterface::class);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $controller->expects($this->once())->method('createForm')->willReturn($form);
        $controller->expects($this->once())->method('render')
            ->with('contact/contact.html.twig', ['form' => $form])
            ->willReturn(new Response('form'));

        $response = $controller->contact(new Request(), $mailer);
        $this->assertSame('form', $response->getContent());
    }

    public function testContactUsesMarketingServiceAddress(): void
    {
        $controller = $this->getMockBuilder(ContactController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $mailer = $this->createMock(MailerInterface::class);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturnCallback(function (ContactDTO $dto = null) {
            return $dto;
        });

        $controller->expects($this->once())
            ->method('createForm')
            ->with(ContactType::class, $this->isInstanceOf(ContactDTO::class))
            ->willReturnCallback(function (string $type, ContactDTO $dto) use ($form) {
                $dto->service = 'marketing';
                $dto->email = 'user@test.fr';
                $dto->object = 'Hello';
                $dto->message = 'Test';
                $dto->name = 'User';
                return $form;
            });

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return $email->getTo()[0]->getAddress() === 'marketing@lacouisine.fr';
            }));

        $controller->expects($this->once())->method('addFlash')->with('success', 'Votre email a bien été envoyé');
        $controller->expects($this->once())->method('redirectToRoute')->with('contact')
            ->willReturn(new RedirectResponse('/contact'));

        $response = $controller->contact(new Request(), $mailer);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testContactUsesAccountantServiceAddress(): void
    {
        $controller = $this->getMockBuilder(ContactController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $mailer = $this->createMock(MailerInterface::class);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturnCallback(function (ContactDTO $dto = null) {
            return $dto;
        });

        $controller->expects($this->once())
            ->method('createForm')
            ->with(ContactType::class, $this->isInstanceOf(ContactDTO::class))
            ->willReturnCallback(function (string $type, ContactDTO $dto) use ($form) {
                $dto->service = 'accountant';
                $dto->email = 'user@test.fr';
                $dto->object = 'Hello';
                $dto->message = 'Test';
                $dto->name = 'User';
                return $form;
            });

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return $email->getTo()[0]->getAddress() === 'accountant@lacouisine.fr';
            }));

        $controller->expects($this->once())->method('addFlash')->with('success', 'Votre email a bien été envoyé');
        $controller->expects($this->once())->method('redirectToRoute')->with('contact')
            ->willReturn(new RedirectResponse('/contact'));

        $response = $controller->contact(new Request(), $mailer);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testContactFallsBackToDefaultService(): void
    {
        $controller = $this->getMockBuilder(ContactController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $mailer = $this->createMock(MailerInterface::class);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturnCallback(function (ContactDTO $dto = null) {
            return $dto;
        });

        $controller->expects($this->once())
            ->method('createForm')
            ->with(ContactType::class, $this->isInstanceOf(ContactDTO::class))
            ->willReturnCallback(function (string $type, ContactDTO $dto) use ($form) {
                $dto->service = 'unknown';
                $dto->email = 'user@test.fr';
                $dto->object = 'Hello';
                $dto->message = 'Test';
                $dto->name = 'User';
                return $form;
            });

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return $email->getTo()[0]->getAddress() === 'support@lacouisine.fr';
            }));

        $controller->expects($this->once())->method('addFlash')->with('success', 'Votre email a bien été envoyé');
        $controller->expects($this->once())->method('redirectToRoute')->with('contact')
            ->willReturn(new RedirectResponse('/contact'));

        $response = $controller->contact(new Request(), $mailer);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}

