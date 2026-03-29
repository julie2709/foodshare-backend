<?php

namespace App\Repository;

use App\Entity\DonationRequest;
use App\Entity\Listing;
use App\Entity\User;
use App\Enum\DonationRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DonationRequest>
 */
class DonationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DonationRequest::class);
    }

    /**
     * Retourne toutes les demandes d'une annonce appartenant bien au propriétaire connecté.
     */
    public function findForOwnerListing(Listing $listing, User $owner): array
    {
        return $this->createQueryBuilder('dr')
            ->join('dr.listing', 'l')
            ->andWhere('dr.listing = :listing')
            ->andWhere('l.user = :owner')
            ->setParameter('listing', $listing)
            ->setParameter('owner', $owner)
            ->orderBy('dr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les autres demandes encore en attente pour une annonce,
     * sauf celle qui vient d'être acceptée.
     */
    public function findOtherPendingByListing(Listing $listing, int $excludedRequestId): array
    {
        return $this->createQueryBuilder('dr')
            ->andWhere('dr.listing = :listing')
            ->andWhere('dr.status = :status')
            ->andWhere('dr.id != :excludedId')
            ->setParameter('listing', $listing)
            ->setParameter('status', DonationRequestStatus::PENDING)
            ->setParameter('excludedId', $excludedRequestId)
            ->orderBy('dr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a déjà une demande "active" sur une annonce.
     * Ici, active = PENDING ou ACCEPTED.
     */
    public function findOneActiveByUserAndListing(User $user, Listing $listing): ?DonationRequest
    {
        return $this->createQueryBuilder('dr')
            ->andWhere('dr.user = :user')
            ->andWhere('dr.listing = :listing')
            ->andWhere('dr.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('listing', $listing)
            ->setParameter('statuses', [
                DonationRequestStatus::PENDING,
                DonationRequestStatus::ACCEPTED,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne la demande acceptée d'une annonce, s'il y en a une.
     */
    public function findAcceptedByListing(Listing $listing): ?DonationRequest
    {
        return $this->createQueryBuilder('dr')
            ->andWhere('dr.listing = :listing')
            ->andWhere('dr.status = :status')
            ->setParameter('listing', $listing)
            ->setParameter('status', DonationRequestStatus::ACCEPTED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

   
    public function findMine(User $user): array
{
    return $this->createQueryBuilder('dr')
        ->leftJoin('dr.listing', 'l')
        ->addSelect('l')
        ->leftJoin('l.listingPhotos', 'lp')
        ->addSelect('lp')
        ->andWhere('dr.user = :user')
        ->setParameter('user', $user)
        ->orderBy('dr.id', 'DESC')
        ->getQuery()
        ->getResult();
}
}