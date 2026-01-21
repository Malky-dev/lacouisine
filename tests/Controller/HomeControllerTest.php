<?php

namespace App\Tests\Controller;

use App\Controller\HomeController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class HomeControllerTest extends TestCase
{
    public function testIndexRendersHome(): void
    {
        $controller = $this->getMockBuilder(HomeController::class)
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('home/index.html.twig', ['controller_name' => 'HomeController'])
            ->willReturn(new Response('home'));

        $response = $controller->index(
            new Request(),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class)
        );

        $this->assertSame('home', $response->getContent());
    }
}



