<?php

namespace App\Tests\Form;

use App\Form\FormListenerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class FormListenerFactoryTest extends TestCase
{
    public function testAutoSlugListener(): void
    {
        $slugger = $this->createMock(SluggerInterface::class);
        $slugger->expects($this->once())
            ->method('slug')
            ->with('Hello World')
            ->willReturn(new \Symfony\Component\String\UnicodeString('Hello-World'));

        $factory = new FormListenerFactory($slugger);
        $listener = $factory->autoSlug('name');

        $form = $this->createMock(FormInterface::class);
        $event = new PreSubmitEvent($form, ['name' => 'Hello World']);

        $listener($event);
        $this->assertSame('hello-world', $event->getData()['slug']);
    }

    public function testAutoDateTimeImmutableListenerSetsCreatedAndUpdated(): void
    {
        $slugger = $this->createMock(SluggerInterface::class);
        $factory = new FormListenerFactory($slugger);
        $listener = $factory->autoDateTimeImmutable();

        $entity = new class {
            private ?int $id = null;
            private ?\DateTimeImmutable $createdAt = null;
            private ?\DateTimeImmutable $updatedAt = null;
            public function getId(): ?int { return $this->id; }
            public function setId(?int $id): void { $this->id = $id; }
            public function setCreatedAt(\DateTimeImmutable $d): void { $this->createdAt = $d; }
            public function setUpdatedAt(\DateTimeImmutable $d): void { $this->updatedAt = $d; }
            public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
            public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
        };

        $form = $this->createMock(FormInterface::class);
        $event = new PostSubmitEvent($form, $entity);
        $listener($event);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // existing entity: should not overwrite createdAt
        $createdAt = $entity->getCreatedAt();
        $entity->setId(10);
        $event2 = new PostSubmitEvent($form, $entity);
        $listener($event2);
        $this->assertSame($createdAt, $entity->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }
}


