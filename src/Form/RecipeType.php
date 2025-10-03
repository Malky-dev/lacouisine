<?php

namespace App\Form;

use App\Entity\Recipe;
use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Nom de la recette',
                'empty_data' => '',
                'constraints' => [ 'required' => true ]
            ])
            ->add('slug', HiddenType::class)
            ->add('content', TextareaType::class, [
                'label' => 'Processus de la recette',
                'empty_data' => ''
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Temps de préparation (en minutes)'
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer'
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, $this->autoSlug(...))
            ->addEventListener(FormEvents::POST_SUBMIT, $this->autoDateTimeImmutable(...))
        ;
    }

    public function autoSlug(PreSubmitEvent $event): void 
    {

        $data = $event->getData();
        $slugger = new AsciiSlugger();
        $data['slug'] = strtolower($slugger->slug($data['title']));
        $event->setData($data);
        
    }

    public function autoDateTimeImmutable(PostSubmitEvent $event): void 
    {

        $data = $event->getData();
        $dateTime = new DateTimeImmutable();

        if (!($data instanceof Recipe)) {
            return;
        }

        if (!$data->getId()) {
            $data->setCreatedAt($dateTime);
        }

        $data->setUpdatedAt($dateTime);
        
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
