<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Recipe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{FileType, HiddenType, SubmitType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeType extends AbstractType
{

    public function __construct(private FormListenerFactory $listenerFactory)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add('title')
            ->add('slug', HiddenType::class)
            ->add('thumbnailFile', FileType::class, [
                'required' => false
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name'
            ])
            ->add('content')
            ->add('duration')
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer'
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, $this->listenerFactory->autoSlug('title'))
            ->addEventListener(FormEvents::POST_SUBMIT, $this->listenerFactory->autoDateTimeImmutable())
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {

        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
        
    }
}
