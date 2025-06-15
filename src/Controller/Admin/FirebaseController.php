<?php

namespace App\Controller\Admin;

use App\Entity\DeviceToken;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class FirebaseController extends AbstractController
{
    private string $firebasePath;
    public function __construct(ParameterBagInterface $params)
    {
        $projectDir = $params->get('kernel.project_dir');

        // Połączenie katalogu głównego z nazwą pliku z ENV
        $this->firebasePath = $projectDir . '/' . ($_ENV['FIREBASE_CREDENTIALS'] ?? 'config/firebase/firebase-credentials.json');
    }
    #[Route('/admin/firebase/send', name: 'admin_firebase_send')]
    public function sendNotification(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder()
            ->add('title', TextType::class, ['label' => 'Tytuł'])
            ->add('body', TextType::class, ['label' => 'Treść'])
            ->add('submit', SubmitType::class, ['label' => 'Wyślij powiadomienie'])
            ->getForm();

        $form->handleRequest($request);

        $sent = false;
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $messaging = (new Factory())
                    ->withServiceAccount($this->firebasePath)
                    ->createMessaging();

                $tokens = $em->getRepository(DeviceToken::class)->findAll();

                foreach ($tokens as $token) {
                    try {
                    $message = CloudMessage::withTarget('token', $token->getToken())
                        ->withNotification(Notification::create($data['title'], $data['body']));

                    $messaging->send($message);
                    } catch (NotFound | InvalidArgument $e) {
                        $em->remove($token);
                        $output[] = "Usunięto nieprawidłowy token: {$token->getToken()}";
                    } catch (\Throwable $e) {
                        $output[] = "Błąd dla tokenu {$token->getToken()}: " . $e->getMessage();
                    }
                }

                $sent = true;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('admin/firebase/send.html.twig', [
            'form' => $form->createView(),
            'sent' => $sent,
            'error' => $error,
            'output' => $output ?? [],
        ]);
    }

    #[Route('/admin/firebase/send-selected', name: 'admin_firebase_send_selected')]
    public function sendSelectedNotification(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder()
            ->add('title', TextType::class, ['label' => 'Tytuł'])
            ->add('body', TextType::class, ['label' => 'Treść'])
            ->add('tokens', EntityType::class, [
                'class' => DeviceToken::class,
                'choice_label' => 'token',
                'label' => 'Wybierz urządzenia',
                'required' => true,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Wyślij tylko do zaznaczonych'])
            ->getForm();

        $form->handleRequest($request);

        $sent = false;
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $selectedTokens = $data['tokens'];

            try {
                $messaging = (new Factory())
                    ->withServiceAccount($this->firebasePath)
                    ->createMessaging();

                foreach ($selectedTokens as $token) {
                    try {
                        $message = CloudMessage::withTarget('token', $token->getToken())
                            ->withNotification(Notification::create($data['title'], $data['body']));

                        $messaging->send($message);
                    } catch (NotFound | InvalidArgument $e) {
                        $em->remove($token);
                        $output[] = "Usunięto nieprawidłowy token: {$token->getToken()}";
                    } catch (\Throwable $e) {
                        $output[] = "Błąd dla tokenu {$token->getToken()}: " . $e->getMessage();
                    }
                }

                $em->flush();
                $sent = true;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('admin/firebase/send_selected.html.twig', [
            'form' => $form->createView(),
            'sent' => $sent,
            'error' => $error,
            'output' => $output ?? [],
        ]);
    }
}