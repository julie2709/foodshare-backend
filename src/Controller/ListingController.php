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

        // ajout de test temporaire
        return $this->json([
            'method' => $request->getMethod(),
            'content_type' => $request->headers->get('content-type'),
            'content_length' => $request->headers->get('content-length'),
            'raw_len' => strlen($request->getContent()),
            'request_all' => $request->request->all(),
            'files_all' => $request->files->all(),
            ]);
        // return $this->json([
        //     'content_type' => $request->headers->get('content-type'),
        //     'request_all' => $request->request->all(),
        //     'files_keys' => array_keys($request->files->all()),
        //     ]);

        // Champs texte (multipart/form-data)
        $title = trim((string) $request->request->get('title'));
        $category = trim((string) $request->request->get('category'));
        $postalCode = trim((string) $request->request->get('postalCode'));

        if ($title === '' || $category === '' || $postalCode === '') {
            return $this->json(['message' => 'title, category et postalCode sont obligatoires'], 422);
        }

        // Lyon/périph : simple règle (commence par 69)
        if (!preg_match('/^69/', $postalCode)) {
            return $this->json(['message' => 'Zone non autorisée (postalCode doit commencer par 69)'], 422);
        }

        $listing = new Listing();
        $listing->setUser($user);
        $listing->setTitle($title);
        $listing->setCategory($category);
        $listing->setPostalCode($postalCode);

        // Optionnels
        $listing->setDescription($request->request->get('description'));
        $listing->setQuantity($request->request->get('quantity'));
        $listing->setCity($request->request->get('city'));
        $listing->setPickupInfo($request->request->get('pickupInfo'));

        // expiryDate optionnelle (YYYY-MM-DD)
        $expiryDate = $request->request->get('expiryDate');
        if ($expiryDate) {
            try {
                // Ton champ est DateTime mutable => new \DateTime() ok
                $d = new \DateTime($expiryDate);
                $today = new \DateTime('today');
                if ($d < $today) {
                    return $this->json(['message' => 'expiryDate ne peut pas être dans le passé'], 422);
                }
                $listing->setExpiryDate($d);
            } catch (\Throwable) {
                return $this->json(['message' => 'expiryDate invalide (attendu: YYYY-MM-DD)'], 422);
            }
        }

        // persist listing pour obtenir l'id
        $em->persist($listing);
        $em->flush();

        // Upload photos : clé "photos" ou "photos[]" selon Angular
        $photos = $request->files->all('photos');
        if (!$photos) {
            $photos = $request->files->get('photos'); // fallback
            if ($photos instanceof UploadedFile) {
                $photos = [$photos];
            }
        }

        if (is_array($photos) && count($photos) > 0) {
            if (count($photos) > 3) {
                return $this->json(['message' => 'Maximum 3 photos'], 422);
            }

            foreach ($photos as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $error = $this->validateImage($file);
                if ($error !== null) {
                    return $this->json(['message' => $error], 422);
                }

                $relativePath = $this->storeListingPhoto($file, (int) $listing->getId());

                $photo = new ListingPhoto();
                $photo->setUrl($relativePath);
                // createdAt déjà géré dans __construct()

                $listing->addListingPhoto($photo); // cascade persist recommandé
            }

            $em->flush();
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json($this->serializeListing($listing, $baseUrl, false), 201);
    }

    private function validateImage(UploadedFile $file): ?string
    {
        if ($file->getSize() !== null && $file->getSize() > 5 * 1024 * 1024) {
            return 'Fichier trop volumineux (max 5MB).';
        }

        $mime = $file->getMimeType();
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!$mime || !in_array($mime, $allowed, true)) {
            return 'Format non supporté (jpeg/png/webp).';
        }

        return null;
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
}