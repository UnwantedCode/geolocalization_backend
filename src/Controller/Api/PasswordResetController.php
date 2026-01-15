<?php

namespace App\Controller\Api;

use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private string $mailerEmail
    ) {}

    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgot(
        Request $request,
        UserRepository $userRepo,
        PasswordResetTokenRepository $tokenRepo,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        $responseMessage = ['message' => 'If the account exists, we have sent a reset code.'];

        if (!$email) {
            return $this->json($responseMessage, Response::HTTP_OK);
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json($responseMessage, Response::HTTP_OK);
        }

        // Delete any existing tokens for this email
        $tokenRepo->deleteTokensByEmail($email);

        // Generate 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create new token
        $token = new PasswordResetToken();
        $token->setEmail($email);
        $token->setCode($code);

        $entityManager->persist($token);
        $entityManager->flush();

        // Send email
        $emailMessage = (new Email())
            ->from($this->mailerEmail)
            ->to($email)
            ->subject('Password Reset Code')
            ->text("Your password reset code is: $code\n\nThis code is valid for 1 hour.")
            ->html("<p>Your password reset code is: <strong>$code</strong></p><p>This code is valid for 1 hour.</p>");

        try {
            $mailer->send($emailMessage);
        } catch (\Exception $e) {
            // Log error but don't expose it to user
        }

        return $this->json($responseMessage, Response::HTTP_OK);
    }

    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(
        Request $request,
        UserRepository $userRepo,
        PasswordResetTokenRepository $tokenRepo,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (!$email || !$code || !$newPassword) {
            return $this->json(['error' => 'Email, code and newPassword are required'], Response::HTTP_BAD_REQUEST);
        }

        $token = $tokenRepo->findValidTokenByEmail($email);

        if (!$token) {
            return $this->json(['error' => 'Invalid or expired code'], Response::HTTP_BAD_REQUEST);
        }

        // Check if code matches
        if ($token->getCode() !== $code) {
            $token->incrementAttempts();
            $entityManager->flush();

            if ($token->hasExceededMaxAttempts()) {
                return $this->json(['error' => 'Too many attempts. Please request a new code.'], Response::HTTP_BAD_REQUEST);
            }

            $remainingAttempts = 5 - $token->getAttempts();
            return $this->json(['error' => "Invalid code. $remainingAttempts attempts remaining."], Response::HTTP_BAD_REQUEST);
        }

        // Find user and update password
        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        // Delete the used token
        $tokenRepo->deleteTokensByEmail($email);

        $entityManager->flush();

        return $this->json(['message' => 'Password has been reset successfully'], Response::HTTP_OK);
    }
}