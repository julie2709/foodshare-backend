<?php

namespace App\Repository;

use App\Entity\DonationRequest;
use App\Entity\Listing;
use App\Entity\User;
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

    public function findOtherPendingByListing(Listing $listing, int $excludedRequestId): array
    {
        return $this->createQueryBuilder('dr')
            ->andWhere('dr.listing = :listing')
            ->andWhere('dr.status = :status')
            ->andWhere('dr.id != :excludedId')
            ->setParameter('listing', $listing)
            ->setParameter('status', DonationRequest::STATUS_PENDING)
            ->setParameter('excludedId', $excludedRequestId)
            ->orderBy('dr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneActiveByUserAndListing(User $user, Listing $listing): ?DonationRequest
    {
        return $this->createQueryBuilder('dr')
            ->andWhere('dr.user = :user')
            ->andWhere('dr.listing = :listing')
            ->andWhere('dr.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('listing', $listing)
            ->setParameter('statuses', [
                DonationRequest::STATUS_PENDING,
                DonationRequest::STATUS_ACCEPTED,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}