<?php

namespace App\Service;

use App\Entity\DonationRequest;
use App\Entity\Listing;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\DonationRequestStatus;
use App\Enum\ListingStatus;
use App\Repository\DonationRequestRepository;
use App\Repository\ListingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DonationRequestService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DonationRequestRepository $donationRequestRepository,
        private ListingRepository $listingRepository,
        private NotificationService $notificationService
    ) {
    }

    public function createRequest(int $listingId, User $currentUser, ?string $message): DonationRequest
    {
        $listing = $this->listingRepository->find($listingId);

        if (!$listing) {
            throw new NotFoundHttpException('Annonce introuvable.');
        }

        $owner = $listing->getUser();

        if ($owner?->getId() === $currentUser->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez pas demander votre propre annonce.');
        }

        if ($listing->getStatus() !== ListingStatus::DISPONIBLE) {
            throw new BadRequestHttpException('Cette annonce n’est plus disponible.');
        }

        $existingRequest = $this->donationRequestRepository->findOneActiveByUserAndListing($currentUser, $listing);
        if ($existingRequest) {
            throw new BadRequestHttpException('Vous avez déjà une demande active pour cette annonce.');
        }

        $donationRequest = new DonationRequest();
        $donationRequest->setListing($listing);
        $donationRequest->setUser($currentUser);
        $donationRequest->setMessage($message);
        $donationRequest->setStatus(DonationRequestStatus::PENDING);

        $this->em->persist($donationRequest);

        /**
         * Flush ici pour garantir que l'ID de la demande existe
         * avant d'être utilisé dans la notification/data.
         */
        $this->em->flush();

        if ($owner) {
            $this->notificationService->create(
                recipient: $owner,
                type: Notification::TYPE_REQUEST_CREATED,
                title: 'Nouvelle demande reçue',
                message: sprintf(
                    '%s souhaite récupérer votre don "%s".',
                    $currentUser->getPseudo(),
                    $listing->getTitle()
                ),
                actor: $currentUser,
                listing: $listing,
                donationRequest: $donationRequest,
                data: [
                    'listingId' => $listing->getId(),
                    'donationRequestId' => $donationRequest->getId(),
                ]
            );
        }

        $this->em->flush();

        return $donationRequest;
    }

    public function getRequestsForOwnerListing(int $listingId, User $currentUser): array
    {
        $listing = $this->listingRepository->find($listingId);

        if (!$listing) {
            throw new NotFoundHttpException('Annonce introuvable.');
        }

        if ($listing->getUser()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        return $this->donationRequestRepository->findForOwnerListing($listing, $currentUser);
    }

    public function acceptRequest(int $requestId, User $currentUser): DonationRequest
    {
        $donationRequest = $this->donationRequestRepository->find($requestId);

        if (!$donationRequest) {
            throw new NotFoundHttpException('Demande introuvable.');
        }

        $listing = $donationRequest->getListing();
        if (!$listing) {
            throw new BadRequestHttpException('Annonce introuvable.');
        }

        if ($listing->getUser()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        if ($donationRequest->getStatus() !== DonationRequestStatus::PENDING) {
            throw new BadRequestHttpException('Seule une demande PENDING peut être acceptée.');
        }

        if ($listing->getStatus() !== ListingStatus::DISPONIBLE) {
            throw new BadRequestHttpException('Cette annonce n’est plus disponible.');
        }

        $this->em->wrapInTransaction(function () use ($donationRequest, $listing, $currentUser) {
            $donationRequest->setStatus(DonationRequestStatus::ACCEPTED);
            $listing->setStatus(ListingStatus::RESERVEE);

            $otherPendingRequests = $this->donationRequestRepository
                ->findOtherPendingByListing($listing, $donationRequest->getId());

            foreach ($otherPendingRequests as $otherRequest) {
                $otherRequest->setStatus(DonationRequestStatus::REFUSED);

                $otherUser = $otherRequest->getUser();
                if ($otherUser) {
                    $this->notificationService->create(
                        recipient: $otherUser,
                        type: Notification::TYPE_REQUEST_REFUSED,
                        title: 'Demande refusée',
                        message: sprintf(
                            'Votre demande pour "%s" a été refusée.',
                            $listing->getTitle()
                        ),
                        actor: $currentUser,
                        listing: $listing,
                        donationRequest: $otherRequest,
                        data: [
                            'listingId' => $listing->getId(),
                            'donationRequestId' => $otherRequest->getId(),
                        ]
                    );
                }
            }

            $acceptedUser = $donationRequest->getUser();
            if ($acceptedUser) {
                $this->notificationService->create(
                    recipient: $acceptedUser,
                    type: Notification::TYPE_REQUEST_ACCEPTED,
                    title: 'Demande acceptée',
                    message: sprintf(
                        'Votre demande pour "%s" a été acceptée.',
                        $listing->getTitle()
                    ),
                    actor: $currentUser,
                    listing: $listing,
                    donationRequest: $donationRequest,
                    data: [
                        'listingId' => $listing->getId(),
                        'donationRequestId' => $donationRequest->getId(),
                    ]
                );
            }

            $this->em->flush();
        });

        return $donationRequest;
    }

    public function refuseRequest(int $requestId, User $currentUser): DonationRequest
    {
        $donationRequest = $this->donationRequestRepository->find($requestId);

        if (!$donationRequest) {
            throw new NotFoundHttpException('Demande introuvable.');
        }

        $listing = $donationRequest->getListing();
        if (!$listing) {
            throw new BadRequestHttpException('Annonce introuvable.');
        }

        if ($listing->getUser()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        if ($donationRequest->getStatus() !== DonationRequestStatus::PENDING) {
            throw new BadRequestHttpException('Seule une demande PENDING peut être refusée.');
        }

        $donationRequest->setStatus(DonationRequestStatus::REFUSED);

        $requestUser = $donationRequest->getUser();
        if ($requestUser) {
            $this->notificationService->create(
                recipient: $requestUser,
                type: Notification::TYPE_REQUEST_REFUSED,
                title: 'Demande refusée',
                message: sprintf(
                    'Votre demande pour "%s" a été refusée.',
                    $listing->getTitle()
                ),
                actor: $currentUser,
                listing: $listing,
                donationRequest: $donationRequest,
                data: [
                    'listingId' => $listing->getId(),
                    'donationRequestId' => $donationRequest->getId(),
                ]
            );
        }

        $this->em->flush();

        return $donationRequest;
    }

    public function cancelRequest(int $requestId, User $currentUser): DonationRequest
    {
        $donationRequest = $this->donationRequestRepository->find($requestId);

        if (!$donationRequest) {
            throw new NotFoundHttpException('Demande introuvable.');
        }

        if ($donationRequest->getUser()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        if ($donationRequest->getStatus() !== DonationRequestStatus::PENDING) {
            throw new BadRequestHttpException('Seule une demande PENDING peut être annulée.');
        }

        $donationRequest->setStatus(DonationRequestStatus::CANCELLED);

        $listing = $donationRequest->getListing();
        $owner = $listing?->getUser();

        if ($owner) {
            $this->notificationService->create(
                recipient: $owner,
                type: Notification::TYPE_REQUEST_CANCELLED,
                title: 'Demande annulée',
                message: sprintf(
                    '%s a annulé sa demande pour "%s".',
                    $currentUser->getPseudo(),
                    $listing?->getTitle()
                ),
                actor: $currentUser,
                listing: $listing,
                donationRequest: $donationRequest,
                data: [
                    'listingId' => $listing?->getId(),
                    'donationRequestId' => $donationRequest->getId(),
                ]
            );
        }

        $this->em->flush();

        return $donationRequest;
    }

    /**
     * Ici on rend l'annonce à nouveau disponible.
     * Pour rester cohérent métier, on rétrograde aussi l'ancienne demande ACCEPTED en CANCELLED.
     * Tu peux choisir REFUSED à la place, mais il faut choisir un comportement clair.
     */
    public function markListingAsAvailableAgain(int $listingId, User $currentUser): Listing
    {
        $listing = $this->listingRepository->find($listingId);

        if (!$listing) {
            throw new NotFoundHttpException('Annonce introuvable.');
        }

        if ($listing->getUser()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        if ($listing->getStatus() === ListingStatus::DONNEE) {
            throw new BadRequestHttpException('Une annonce déjà donnée ne peut pas être remise disponible.');
        }

        $acceptedRequest = $this->donationRequestRepository->findAcceptedByListing($listing);

        $this->em->wrapInTransaction(function () use ($listing, $acceptedRequest, $currentUser) {
            $listing->setStatus(ListingStatus::DISPONIBLE);

            if ($acceptedRequest) {
                $acceptedRequest->setStatus(DonationRequestStatus::CANCELLED);

                $acceptedUser = $acceptedRequest->getUser();
                if ($acceptedUser) {
                    $this->notificationService->create(
                        recipient: $acceptedUser,
                        type: Notification::TYPE_REQUEST_CANCELLED,
                        title: 'Réservation annulée',
                        message: sprintf(
                            'Le don "%s" a été remis disponible par son propriétaire.',
                            $listing->getTitle()
                        ),
                        actor: $currentUser,
                        listing: $listing,
                        donationRequest: $acceptedRequest,
                        data: [
                            'listingId' => $listing->getId(),
                            'donationRequestId' => $acceptedRequest->getId(),
                        ]
                    );
                }
            }

            $this->em->flush();
        });

        return $listing;
    }

    /**
     * Version plus stricte :
     * on n'autorise le passage à DONNEE que si l'annonce est RESERVEE.
     * C'est plus cohérent avec ton workflow métier.
     */
    public function markListingAsGiven(int $listingId, User $currentUser): Listing
    {
        $listing = $this->listingRepository->find($listingId);

        if (!$listing) {
            throw new NotFoundHttpException('Annonce introuvable.');
        }

        if ($listing->getUser()?->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        if ($listing->getStatus() === ListingStatus::DONNEE) {
            throw new BadRequestHttpException('Cette annonce est déjà marquée comme donnée.');
        }

        if ($listing->getStatus() !== ListingStatus::RESERVEE) {
            throw new BadRequestHttpException('Seule une annonce RESERVEE peut être marquée comme donnée.');
        }

        $listing->setStatus(ListingStatus::DONNEE);
        $this->em->flush();

        return $listing;
    }
}