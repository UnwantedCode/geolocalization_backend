<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        $payload = $event->getData();
        $user = $event->getUser();
        // add email to token
        $payload['email'] = $user->getEmail();
        $payload['id'] = $user->getId();

        $event->setData($payload);
    }
}