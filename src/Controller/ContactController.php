<?php

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Form\ContactType;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {

        $data = new ContactDTO();

        // TEMPORARY DATA FOR TEST ::::: START

        $data->name = 'Provencal le gaulois';
        $data->object = 'Sire, on en a gros !!!';
        $data->email = 'email@plg.fr';
        $data->message = 'On veut être considéré en tant que tel';

        // TEMPORARY DATA FOR TEST ::::: END

        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $serviceEmailAdrress = 'support@lacouisine.fr';

            switch ($data->service) {
                case 'support':
                    $serviceEmailAdrress = 'support@lacouisine.fr';
                    break;

                case 'marketing':
                    $serviceEmailAdrress = 'marketing@lacouisine.fr';
                    break;

                case 'accountant':
                    $serviceEmailAdrress = 'accountant@lacouisine.fr';
                    break;
                
                default:
                    $serviceEmailAdrress = 'support@lacouisine.fr';
                    break;
            }

            try {

                $mail = (new TemplatedEmail())
                    ->to($serviceEmailAdrress)
                    ->from($data->email)
                    ->subject($data->object)
                    ->htmlTemplate('emails/contact.html.twig')
                    ->context(['data' => $data]);

                $mailer->send($mail);

                $this->addFlash('success', 'Votre email a bien été envoyé');
    
                return $this->redirectToRoute('contact');

            } catch (Exception $e) {
                $this->addFlash('danger', 'Impossible d\'envoyer votre email');
            }
        }

        return $this->render('contact/contact.html.twig', [
            'form' => $form
        ]);
    }
}