<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\DeviceToken;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MessageNotificationService
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
     * Send notification to all group members except the sender
     */
    public function sendGroupMessageNotification(Group $group, User $sender, string $messageContent): void
    {
        // Pobierz tokeny wszystkich członków grupy (poza nadawcą)
        $tokens = $this->em->getRepository(DeviceToken::class)
            ->createQueryBuilder('dt')
            ->join('dt.user', 'u')
            ->join('u.groups', 'g')
            ->where('g.id = :groupId')
            ->andWhere('u.id != :senderId')
            ->setParameter('groupId', $group->getId())
            ->setParameter('senderId', $sender->getId())
            ->getQuery()
            ->getResult();

        if (empty($tokens)) {
            return; // Brak tokenów do wysłania
        }

        $messaging = (new Factory())
            ->withServiceAccount($this->firebasePath)
            ->createMessaging();

        $title = sprintf('New message in %s', $group->getName());
        $body = sprintf('%s: %s', $sender->getUsername(),
            mb_strlen($messageContent) > 50 ? mb_substr($messageContent, 0, 50) . '...' : $messageContent
        );

        foreach ($tokens as $deviceToken) {
            try {
                $message = CloudMessage::withTarget('token', $deviceToken->getToken())
                    ->withNotification(Notification::create($title, $body));
                $messaging->send($message);
            } catch (NotFound | InvalidArgument $e) {
                // Usuń nieprawidłowy token
                $this->em->remove($deviceToken);
            } catch (\Throwable $e) {
                // Log error but continue (nie przerywamy wysyłki dla wszystkich)
                // TODO: Add proper logging
            }
        }

        $this->em->flush(); // Flush usunięć nieprawidłowych tokenów
    }
}
