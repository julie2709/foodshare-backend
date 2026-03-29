<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\DonationRequestService;
use App\Entity\DonationRequest;
use App\Entity\Listing;
use App\Entity\User;
use App\Repository\DonationRequestRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

class DonationRequestServiceTest extends TestCase
{
    public function testAcceptRequestChangesStatusesCorrectly(): void
    {
        // =========================
        // 1. MOCKS
        // =========================
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(DonationRequestRepository::class);
        $notificationService = $this->createMock(NotificationService::class);

        // =========================
        // 2. USERS
        // =========================
        $owner = $this->createMock(User::class);
        $requester = $this->createMock(User::class);
        $otherUser = $this->createMock(User::class);

        $owner->method('getId')->willReturn(1);
        $requester->method('getId')->willReturn(2);
        $otherUser->method('getId')->willReturn(3);

        // =========================
        // 3. LISTING
        // =========================
        $listing = $this->createMock(Listing::class);

        $listing->method('getOwner')->willReturn($owner);

        // On vérifie que le statut passe à RESERVEE
        $listing->expects($this->once())
            ->method('setStatus')
            ->with('RESERVEE');

        // =========================
        // 4. DEMANDE ACCEPTÉE
        // =========================
        $acceptedRequest = $this->createMock(DonationRequest::class);

        $acceptedRequest->method('getId')->willReturn(10);
        $acceptedRequest->method('getListing')->willReturn($listing);
        $acceptedRequest->method('getRequester')->willReturn($requester);

        $acceptedRequest->expects($this->once())
            ->method('setStatus')
            ->with('ACCEPTED');

        // =========================
        // 5. AUTRES DEMANDES
        // =========================
        $otherRequest1 = $this->createMock(DonationRequest::class);
        $otherRequest2 = $this->createMock(DonationRequest::class);

        $otherRequest1->expects($this->once())
            ->method('setStatus')
            ->with('REFUSED');

        $otherRequest2->expects($this->once())
            ->method('setStatus')
            ->with('REFUSED');

        // Le repository retourne les autres demandes
        $repository->method('findOtherPendingByListing')
            ->with($listing, 10)
            ->willReturn([$otherRequest1, $otherRequest2]);

        // =========================
        // 6. ENTITY MANAGER
        // =========================
        $entityManager->expects($this->atLeastOnce())
            ->method('flush');

        // =========================
        // 7. SERVICE À TESTER
        // =========================
        $service = new DonationRequestService(
            $entityManager,
            $repository,
            $notificationService
        );

        // =========================
        // 8. EXECUTION
        // =========================
        $service->acceptRequest($acceptedRequest, $owner);

        // =========================
        // 9. ASSERT FINAL
        // =========================
        $this->assertTrue(true);
    }
}