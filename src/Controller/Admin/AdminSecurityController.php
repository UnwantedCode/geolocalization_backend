<?php
namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AdminSecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard'); // Przekierowanie, jeśli użytkownik jest zalogowany
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
    #[Route('/admin/login-check', name: 'admin_login_check', methods: ['POST'])]
    public function loginCheck(): void
    {
        // Symfony obsłuży automatycznie dane logowania
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): Response
    {
        // Symfony automatycznie obsłuży wylogowanie
        return $this->redirectToRoute('admin_login');

    }
}

