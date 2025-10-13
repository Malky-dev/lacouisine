<?php

namespace App\Form;

use DateTimeImmutable;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\String\Slugger\SluggerInterface;

class FormListenerFactory {

    public function __construct(private SluggerInterface $slugger)
    {
        
    }

    public function autoSlug(string $field): callable 
    {
        return function (PreSubmitEvent $event) use ($field): void {

            $data = $event->getData();
            $data['slug'] = strtolower($this->slugger->slug($data[$field]));
            $event->setData($data);

        };
        
    }

    public function autoDateTimeImmutable(): callable 
    {
        
        return function (PostSubmitEvent $event): void {

            $data = $event->getData();
            $dateTime = new DateTimeImmutable();

            if (!$data->getId()) {
                $data->setCreatedAt($dateTime);
            }

            $data->setUpdatedAt($dateTime);
            
        };

    }
    
}