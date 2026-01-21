<?php

namespace App\Tests\Form;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use App\Form\CategoryType;
use App\Form\ContactType;
use App\Form\FormListenerFactory;
use App\Form\RecipeType;
use App\Form\RegistrationFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FormTypesTest extends TestCase
{
    public function testCategoryTypeBuildAndConfigure(): void
    {
        $listenerFactory = $this->createMock(FormListenerFactory::class);
        $listenerFactory->method('autoSlug')->willReturn(static fn() => null);
        $listenerFactory->method('autoDateTimeImmutable')->willReturn(static fn() => null);

        $type = new CategoryType($listenerFactory);
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnSelf();

        $builder->expects($this->atLeastOnce())->method('add');
        $builder->expects($this->exactly(2))->method('addEventListener')->withAnyParameters();

        $type->buildForm($builder, []);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);
        $opts = $resolver->resolve();
        $this->assertSame(Category::class, $opts['data_class']);
    }

    public function testRecipeTypeBuildAndConfigure(): void
    {
        $listenerFactory = $this->createMock(FormListenerFactory::class);
        $listenerFactory->method('autoSlug')->willReturn(static fn() => null);
        $listenerFactory->method('autoDateTimeImmutable')->willReturn(static fn() => null);

        $type = new RecipeType($listenerFactory);
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnSelf();

        $type->buildForm($builder, []);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);
        $opts = $resolver->resolve();
        $this->assertSame(Recipe::class, $opts['data_class']);
    }

    public function testContactTypeBuildAndConfigure(): void
    {
        $type = new ContactType();
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();

        $type->buildForm($builder, []);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);
        $opts = $resolver->resolve();
        $this->assertSame(\App\DTO\ContactDTO::class, $opts['data_class']);
    }

    public function testRegistrationFormTypeBuildAndConfigure(): void
    {
        $type = new RegistrationFormType();
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();

        $type->buildForm($builder, []);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);
        $opts = $resolver->resolve();
        $this->assertSame(User::class, $opts['data_class']);
    }
}




