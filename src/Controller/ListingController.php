<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\ListingPhoto;
use App\Repository\ListingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/listings')]
final class ListingController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(ListingRepository $repo, Request $request): JsonResponse
    {
        $listings = $repo->findBy([], ['id' => 'DESC']);
        $baseUrl = $request->getSchemeAndHttpHost();

        $data = array_map(fn(Listing $l) => $this->serializeListing($l, $baseUrl, true), $listings);

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Listing $listing, Request $request): JsonResponse
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json($this->serializeListing($listing, $baseUrl, false));
    }

#[IsGranted('ROLE_USER')]
#[Route('', methods: ['POST'])]
public function create(Request $request, EntityManagerInterface $em): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return $this->json(['message' => 'Non authentifié'], 401);
    }

    $title = trim((string) $request->request->get('title'));
    $category = trim((string) $request->request->get('category'));
    $postalCode = trim((string) $request->request->get('postalCode'));

    if ($title === '' || $category === '' || $postalCode === '') {
        return $this->json(['message' => 'title, category et postalCode sont obligatoires'], 422);
    }

    if (!preg_match('/^69\d{3}$/', $postalCode)) {
        return $this->json(['message' => 'Zone non autorisée (postalCode doit être un code postal du Rhône)'], 422);
    }

    $expiryDate = $request->request->get('expiryDate');
    $parsedExpiryDate = null;

    if ($expiryDate) {
        try {
            $parsedExpiryDate = new \DateTimeImmutable($expiryDate);
            $today = new \DateTimeImmutable('today');

            if ($parsedExpiryDate < $today) {
                return $this->json(['message' => 'expiryDate ne peut pas être dans le passé'], 422);
            }
        } catch (\Throwable) {
            return $this->json(['message' => 'expiryDate invalide (attendu: YYYY-MM-DD)'], 422);
        }
    }

    $photos = $request->files->get('photos');

    if (!$photos) {
        return $this->json(['message' => 'Une photo est obligatoire pour créer une annonce.'], 422);
    }

    if ($photos instanceof UploadedFile) {
        $photos = [$photos];
    }

    if (!is_array($photos) || count($photos) !== 1) {
        return $this->json(['message' => 'Une seule photo est autorisée.'], 422);
    }

    // verif upload php


   $file = $photos[0];

   return $this->json([
    'originalName' => $file->getClientOriginalName(),
    'size_bytes' => $file->getSize(),
    'size_kb' => $file->getSize() !== null ? round($file->getSize() / 1024, 2) : null,
    'size_mb' => $file->getSize() !== null ? round($file->getSize() / 1024 / 1024, 3) : null,
    'mime_detected' => $file->getMimeType(),
    'client_mime' => $file->getClientMimeType(),
    'error_code' => $file->getError(),
]);

        // Vérification erreur upload PHP
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->json([
                'message' => 'Erreur lors de l\'upload du fichier.',
                'error_code' => $file->getError()
            ], 422);
        }

        // Validation personnalisée (taille + mime)
        $error = $this->validateImage($file);
        if ($error !== null) {
            return $this->json(['message' => $error], 422);
        }

    $listing = new Listing();
    $listing->setUser($user);
    $listing->setTitle($title);
    $listing->setCategory($category);
    $listing->setPostalCode($postalCode);
    $listing->setDescription($request->request->get('description'));
    $listing->setQuantity($request->request->get('quantity'));
    $listing->setCity($request->request->get('city'));
    $listing->setPickupInfo($request->request->get('pickupInfo'));

    if ($parsedExpiryDate) {
        $listing->setExpiryDate($parsedExpiryDate);
    }

    $em->persist($listing);
    $em->flush();

    $relativePath = $this->storeListingPhoto($file, (int) $listing->getId());

    $photo = new ListingPhoto();
    $photo->setUrl($relativePath);
    $listing->addListingPhoto($photo);

    $em->flush();

    $baseUrl = $request->getSchemeAndHttpHost();
    return $this->json($this->serializeListing($listing, $baseUrl, false), 201);
}

    private function storeListingPhoto(UploadedFile $file, int $listingId): string
    {
        // Dossier final : public/uploads/listings/{id}/
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';
        $dir = $publicDir . '/uploads/listings/' . $listingId;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = $file->guessExtension() ?: 'bin';
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

        $file->move($dir, $name);

        // Stocké en DB (chemin relatif accessible)
        return 'uploads/listings/' . $listingId . '/' . $name;
    }

    private function serializeListing(Listing $l, string $baseUrl, bool $isList): array
    {
        $photos = [];
        foreach ($l->getListingPhotos() as $p) {
            $url = $p->getUrl();
            $photos[] = [
                'id' => $p->getId(),
                'url' => $url,
                'publicUrl' => $url ? ($baseUrl . '/' . ltrim($url, '/')) : null,
            ];
        }

        return [
            'id' => $l->getId(),
            'title' => $l->getTitle(),
            'description' => $l->getDescription(),
            'category' => $l->getCategory(),
            'quantity' => $l->getQuantity(),
            'expiryDate' => $l->getExpiryDate()?->format('Y-m-d'),
            'postalCode' => $l->getPostalCode(),
            'city' => $l->getCity(),
            'pickupInfo' => $l->getPickupInfo(),
            'status' => $l->getStatus(),
            'createdAt' => $l->getCreatedAt()?->format('Y-m-d H:i:s'),
            'user' => $isList ? null : [
                'id' => $l->getUser()?->getId(),
                'email' => $l->getUser()?->getEmail(),
                'pseudo' => method_exists($l->getUser(), 'getPseudo') ? $l->getUser()?->getPseudo() : null,
            ],
            'photos' => $photos,
        ];
    }

    private function validateImage(UploadedFile $file): ?string
        {
            if ($file->getSize() !== null && $file->getSize() > 10 * 1024 * 1024) {
                return 'Fichier trop volumineux (max 5MB).';
            }

            $mime = $file->getMimeType();
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

            if (!$mime || !in_array($mime, $allowed, true)) {
                return 'Format non supporté (jpeg/png/webp).';
            }

            return null;
        }


// endpoint PUT
#[IsGranted('ROLE_USER')]
#[Route('/{id}', methods: ['PUT'])]
public function update(Listing $listing, Request $request, EntityManagerInterface $em): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return $this->json(['message' => 'Non authentifié'], 401);
    }

    if ($listing->getUser()?->getId() !== $user->getId()) {
        return $this->json(['message' => 'Accès refusé'], 403);
    }

    $title = trim((string) $request->request->get('title', $listing->getTitle()));
    $category = trim((string) $request->request->get('category', $listing->getCategory()));
    $postalCode = trim((string) $request->request->get('postalCode', $listing->getPostalCode()));

    if ($title === '' || $category === '' || $postalCode === '') {
        return $this->json(['message' => 'title, category et postalCode sont obligatoires'], 422);
    }

    if (!preg_match('/^69\d{3}$/', $postalCode)) {
        return $this->json(['message' => 'Zone non autorisée (postalCode doit être un code postal du Rhône)'], 422);
    }

    $expiryDate = $request->request->get('expiryDate');
    $parsedExpiryDate = $listing->getExpiryDate();

    if ($expiryDate !== null && $expiryDate !== '') {
        try {
            $parsedExpiryDate = new \DateTimeImmutable($expiryDate);
            $today = new \DateTimeImmutable('today');

            if ($parsedExpiryDate < $today) {
                return $this->json(['message' => 'expiryDate ne peut pas être dans le passé'], 422);
            }
        } catch (\Throwable) {
            return $this->json(['message' => 'expiryDate invalide (attendu: YYYY-MM-DD)'], 422);
        }
    }

    $listing->setTitle($title);
    $listing->setCategory($category);
    $listing->setPostalCode($postalCode);
    $listing->setDescription($request->request->get('description', $listing->getDescription()));
    $listing->setQuantity($request->request->get('quantity', $listing->getQuantity()));
    $listing->setCity($request->request->get('city', $listing->getCity()));
    $listing->setPickupInfo($request->request->get('pickupInfo', $listing->getPickupInfo()));
    $listing->setExpiryDate($parsedExpiryDate);

    // Photo facultative en update : si fournie, on remplace l’ancienne
    $photos = $request->files->get('photos');

    if ($photos instanceof UploadedFile) {
        $photos = [$photos];
    }

    if ($photos !== null) {
        if (!is_array($photos) || count($photos) !== 1) {
            return $this->json([
                'message' => 'Une seule photo est autorisée.'
            ], 422);
        }

        $file = $photos[0];

        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'Fichier photo invalide.'], 422);
        }

        $error = $this->validateImage($file);
        if ($error !== null) {
            return $this->json(['message' => $error], 422);
        }

        // Supprimer anciennes photos physiques + DB
        foreach ($listing->getListingPhotos() as $oldPhoto) {
            $this->deletePhysicalPhoto($oldPhoto->getUrl());
            $em->remove($oldPhoto);
        }
        $em->flush();

        // Ajouter la nouvelle photo
        $relativePath = $this->storeListingPhoto($file, (int) $listing->getId());

        $photo = new ListingPhoto();
        $photo->setUrl($relativePath);
        $listing->addListingPhoto($photo);
    }

    $em->flush();

    $baseUrl = $request->getSchemeAndHttpHost();
    return $this->json($this->serializeListing($listing, $baseUrl, false), 200);
}

// Endponit Delete
#[IsGranted('ROLE_USER')]
#[Route('/{id}', methods: ['DELETE'])]
public function delete(Listing $listing, EntityManagerInterface $em): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return $this->json(['message' => 'Non authentifié'], 401);
    }

    if ($listing->getUser()?->getId() !== $user->getId()) {
        return $this->json(['message' => 'Accès refusé'], 403);
    }

    foreach ($listing->getListingPhotos() as $photo) {
        $this->deletePhysicalPhoto($photo->getUrl());
    }

    $this->deleteListingDirectory((int) $listing->getId());

    $em->remove($listing);
    $em->flush();

    return $this->json(['message' => 'Annonce supprimée avec succès'], 200);
}

// Helpers
private function deletePhysicalPhoto(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $fullPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

private function deleteListingDirectory(int $listingId): void
{
    $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/listings/' . $listingId;

    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $fullPath = $dir . '/' . $file;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    @rmdir($dir);
}
}