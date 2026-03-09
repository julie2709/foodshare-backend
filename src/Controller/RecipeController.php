<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\RecipePhoto;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/recipes')]
final class RecipeController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(RecipeRepository $repo, Request $request): JsonResponse
    {
        $recipes = $repo->findBy([], ['id' => 'DESC']);
        $baseUrl = $request->getSchemeAndHttpHost();

        $data = array_map(
            fn(Recipe $r) => $this->serializeRecipe($r, $baseUrl),
            $recipes
        );

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Recipe $recipe, Request $request): JsonResponse
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json($this->serializeRecipe($recipe, $baseUrl));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $title = trim((string) $request->request->get('title'));
        $ingredients = trim((string) $request->request->get('ingredients'));
        $steps = trim((string) $request->request->get('steps'));

        if ($title === '' || $ingredients === '' || $steps === '') {
            return $this->json([
                'message' => 'title, ingredients et steps sont obligatoires'
            ], 422);
        }

        $timeMinutes = $request->request->get('timeMinutes');
        if ($timeMinutes !== null && $timeMinutes !== '') {
            if (!ctype_digit((string) $timeMinutes) || (int) $timeMinutes < 0) {
                return $this->json([
                    'message' => 'timeMinutes doit être un entier positif ou nul'
                ], 422);
            }
            $timeMinutes = (int) $timeMinutes;
        } else {
            $timeMinutes = null;
        }

        $photos = $request->files->get('photos');

        if (!$photos) {
            return $this->json([
                'message' => 'Une photo est obligatoire pour créer une recette.'
            ], 422);
        }

        if ($photos instanceof UploadedFile) {
            $photos = [$photos];
        }

        if (!is_array($photos) || count($photos) !== 1) {
            return $this->json([
                'message' => 'Une seule photo est autorisée.'
            ], 422);
        }

        $file = $photos[0];

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'message' => 'Fichier photo invalide.'
            ], 422);
        }

        $error = $this->validateImage($file);
        if ($error !== null) {
            return $this->json(['message' => $error], 422);
        }

        $recipe = new Recipe();
        $recipe->setTitle($title);
        $recipe->setIngrédients($ingredients);
        $recipe->setSteps($steps);
        $recipe->setTimeMinutes($timeMinutes);
        $recipe->setDifficulty($request->request->get('difficulty'));
        $recipe->setTags($request->request->get('tags'));

        $em->persist($recipe);
        $em->flush();

        $relativePath = $this->storeRecipePhoto($file, (int) $recipe->getId());

        $photo = new RecipePhoto();
        $photo->setUrl($relativePath);
        $recipe->addRecipePhoto($photo);

        $em->flush();

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json($this->serializeRecipe($recipe, $baseUrl), 201);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(Recipe $recipe, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $title = trim((string) $request->request->get('title', $recipe->getTitle()));
        $ingredients = trim((string) $request->request->get('ingredients', $recipe->getIngrédients()));
        $steps = trim((string) $request->request->get('steps', $recipe->getSteps()));

        if ($title === '' || $ingredients === '' || $steps === '') {
            return $this->json([
                'message' => 'title, ingredients et steps sont obligatoires'
            ], 422);
        }

        $timeMinutesRaw = $request->request->get('timeMinutes', $recipe->getTimeMinutes());
        if ($timeMinutesRaw !== null && $timeMinutesRaw !== '') {
            if (!ctype_digit((string) $timeMinutesRaw) || (int) $timeMinutesRaw < 0) {
                return $this->json([
                    'message' => 'timeMinutes doit être un entier positif ou nul'
                ], 422);
            }
            $timeMinutes = (int) $timeMinutesRaw;
        } else {
            $timeMinutes = null;
        }

        $recipe->setTitle($title);
        $recipe->setIngrédients($ingredients);
        $recipe->setSteps($steps);
        $recipe->setTimeMinutes($timeMinutes);
        $recipe->setDifficulty($request->request->get('difficulty', $recipe->getDifficulty()));
        $recipe->setTags($request->request->get('tags', $recipe->getTags()));

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
                return $this->json([
                    'message' => 'Fichier photo invalide.'
                ], 422);
            }

            $error = $this->validateImage($file);
            if ($error !== null) {
                return $this->json(['message' => $error], 422);
            }

            foreach ($recipe->getRecipePhotos() as $oldPhoto) {
                $this->deletePhysicalRecipePhoto($oldPhoto->getUrl());
                $em->remove($oldPhoto);
            }
            $em->flush();

            $relativePath = $this->storeRecipePhoto($file, (int) $recipe->getId());

            $photo = new RecipePhoto();
            $photo->setUrl($relativePath);
            $recipe->addRecipePhoto($photo);
        }

        $em->flush();

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json($this->serializeRecipe($recipe, $baseUrl), 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Recipe $recipe, EntityManagerInterface $em): JsonResponse
    {
        foreach ($recipe->getRecipePhotos() as $photo) {
            $this->deletePhysicalRecipePhoto($photo->getUrl());
        }

        $this->deleteRecipeDirectory((int) $recipe->getId());

        $em->remove($recipe);
        $em->flush();

        return $this->json(['message' => 'Recette supprimée avec succès'], 200);
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

    private function storeRecipePhoto(UploadedFile $file, int $recipeId): string
    {
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';
        $dir = $publicDir . '/uploads/recipes/' . $recipeId;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = $file->guessExtension() ?: 'bin';
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

        $file->move($dir, $name);

        return 'uploads/recipes/' . $recipeId . '/' . $name;
    }

    private function deletePhysicalRecipePhoto(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $fullPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function deleteRecipeDirectory(int $recipeId): void
    {
        $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/recipes/' . $recipeId;

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

    private function serializeRecipe(Recipe $r, string $baseUrl): array
    {
        $photos = [];
        foreach ($r->getRecipePhotos() as $p) {
            $url = $p->getUrl();
            $photos[] = [
                'id' => $p->getId(),
                'url' => $url,
                'publicUrl' => $url ? ($baseUrl . '/' . ltrim($url, '/')) : null,
            ];
        }

        return [
            'id' => $r->getId(),
            'title' => $r->getTitle(),
            'ingredients' => $r->getIngrédients(),
            'steps' => $r->getSteps(),
            'timeMinutes' => $r->getTimeMinutes(),
            'difficulty' => $r->getDifficulty(),
            'tags' => $r->getTags(),
            'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
            'photos' => $photos,
        ];
    }
}
