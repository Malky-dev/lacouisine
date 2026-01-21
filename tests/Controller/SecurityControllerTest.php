<?php

namespace App\Tests\Controller;

use App\Controller\SecurityController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityControllerTest extends TestCase
{
    public function testLoginRendersTemplate(): void
    {
        $controller = $this->getMockBuilder(SecurityController::class)
            ->onlyMethods(['render'])
            ->getMock();

        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);
        $authUtils->method('getLastUsername')->willReturn('user@example.com');

        $controller->expects($this->once())
            ->method('render')
            ->with('security/login.html.twig', [
                'last_username' => 'user@example.com',
                'error' => null,
            ])
            ->willReturn(new Response('login'));

        $response = $controller->login($authUtils);
        $this->assertSame('login', $response->getContent());
    }

    public function testLogoutThrowsLogicException(): void
    {
        $controller = new SecurityController();

        $this->expectException(\LogicException::class);
        $controller->logout();
    }
}



