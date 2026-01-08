<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\SafetyAlert;
use App\Entity\DeviceToken;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SafetyAlertNotificationService
{
    private string $firebasePath;

    public function __construct(
        private EntityManagerInterface $em,
        ParameterBagInterface $params
    ) {
        $projectDir = $params->get('kernel.project_dir');
        $this->firebasePath = $projectDir . '/' . ($_ENV['FIREBASE_CREDENTIALS'] ?? 'config/firebase/firebase-credentials.json');
    }

    /**
     * Send SOS alert notification to ALL group members (including sender!)
     */
    public function sendAlertNotification(SafetyAlert $alert, Group $group, User $sender): void
    {
        // Pobierz tokeny WSZYSTKICH członków grupy (włącznie z autorem!)
        $tokens = $this->em->getRepository(DeviceToken::class)
            ->createQueryBuilder('dt')
            ->join('dt.user', 'u')
            ->join('u.groups', 'g')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $group->getId())
            ->getQuery()
            ->getResult();

        if (empty($tokens)) {
            return; // Brak tokenów do wysłania
        }

        $messaging = (new Factory())
            ->withServiceAccount($this->firebasePath)
            ->createMessaging();

        $title = sprintf('🚨 SOS Alert in %s', $group->getName());
        $body = sprintf('%s triggered an emergency alert!', $sender->getUsername());

        if ($alert->getMessage()) {
            $body .= ' - ' . (mb_strlen($alert->getMessage()) > 50
                ? mb_substr($alert->getMessage(), 0, 50) . '...'
                : $alert->getMessage());
        }

        // Data payload dla aplikacji mobilnej
        $data = [
            'type' => 'safety_alert',
            'alert_id' => (string) $alert->getId(),
            'group_id' => (string) $group->getId(),
            'user_id' => (string) $sender->getId(),
            'latitude' => (string) $alert->getLocation()->getLatitude(),
            'longitude' => (string) $alert->getLocation()->getLongitude(),
        ];

        foreach ($tokens as $deviceToken) {
            try {
                $message = CloudMessage::withTarget('token', $deviceToken->getToken())
                    ->withNotification(Notification::create($title, $body))
                    ->withData($data);
                $messaging->send($message);
            } catch (NotFound | InvalidArgument $e) {
                // Usuń nieprawidłowy token
                $this->em->remove($deviceToken);
            } catch (\Throwable $e) {
                // Log error but continue
            }
        }

        $this->em->flush(); // Flush usunięć nieprawidłowych tokenów
    }
}
