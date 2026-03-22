<?php

namespace App\Controller;

use App\Entity\DonationRequest;
use App\Entity\User;
use App\Service\DonationRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
final class DonationRequestController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/listings/{id}/requests', methods: ['POST'])]
    public function create(
        int $id,
        Request $request,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $message = isset($data['message']) ? trim((string) $data['message']) : null;

        $donationRequest = $donationRequestService->createRequest($id, $user, $message);

        return $this->json($this->serializeDonationRequest($donationRequest), 201);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/listings/{id}/requests', methods: ['GET'])]
    public function listForOwner(
        int $id,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $donationRequests = $donationRequestService->getRequestsForOwnerListing($id, $user);

        return $this->json(array_map(
            fn (DonationRequest $donationRequest) => $this->serializeDonationRequest($donationRequest),
            $donationRequests
        ));
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/requests/{id}/accept', methods: ['POST'])]
    public function accept(
        int $id,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $donationRequest = $donationRequestService->acceptRequest($id, $user);

        return $this->json($this->serializeDonationRequest($donationRequest));
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/requests/{id}/refuse', methods: ['POST'])]
    public function refuse(
        int $id,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $donationRequest = $donationRequestService->refuseRequest($id, $user);

        return $this->json($this->serializeDonationRequest($donationRequest));
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/requests/{id}/cancel', methods: ['POST'])]
    public function cancel(
        int $id,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $donationRequest = $donationRequestService->cancelRequest($id, $user);

        return $this->json($this->serializeDonationRequest($donationRequest));
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/listings/{id}/mark-available', methods: ['POST'])]
    public function markAvailable(
        int $id,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $listing = $donationRequestService->markListingAsAvailableAgain($id, $user);

        return $this->json([
            'id' => $listing->getId(),
            'status' => $listing->getStatus()?->value,
            'message' => 'Annonce remise en disponible.',
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/listings/{id}/mark-given', methods: ['POST'])]
    public function markGiven(
        int $id,
        DonationRequestService $donationRequestService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $listing = $donationRequestService->markListingAsGiven($id, $user);

        return $this->json([
            'id' => $listing->getId(),
            'status' => $listing->getStatus()?->value,
            'message' => 'Annonce marquée comme donnée.',
        ]);
    }

    /**
     * Sérialisation manuelle propre pour éviter les surprises avec les enums.
     */
    private function serializeDonationRequest(DonationRequest $donationRequest): array
    {
        $listing = $donationRequest->getListing();
        $user = $donationRequest->getUser();

        return [
            'id' => $donationRequest->getId(),
            'message' => $donationRequest->getMessage(),
            'status' => $donationRequest->getStatus()?->value,
            'createdAt' => $donationRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
            'listing' => [
                'id' => $listing?->getId(),
                'title' => $listing?->getTitle(),
                'status' => $listing?->getStatus()?->value,
            ],
            'user' => [
                'id' => $user?->getId(),
                'pseudo' => $user?->getPseudo(),
            ],
        ];
    }
}