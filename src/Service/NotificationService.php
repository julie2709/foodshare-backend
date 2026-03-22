<?php

namespace App\Service;

use App\Entity\DonationRequest;
use App\Entity\Listing;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Crée une notification métier et la persiste.
     * Le flush est volontairement laissé au service appelant
     * pour permettre de grouper plusieurs opérations dans une transaction.
     */
    public function create(
        User $recipient,
        string $type,
        string $title,
        string $message,
        ?User $actor = null,
        ?Listing $listing = null,
        ?DonationRequest $donationRequest = null,
        ?array $data = null
    ): Notification {
        $notification = new Notification();

        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);

        if ($actor) {
            $notification->setActor($actor);
        }

        if ($listing) {
            $notification->setListing($listing);
        }

        if ($donationRequest) {
            $notification->setDonationRequest($donationRequest);
        }

        if ($data !== null) {
            $notification->setData($data);
        }

        $this->em->persist($notification);

        return $notification;
    }
}