<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthController extends AbstractController
{
   
    #[Route('/api/auth/register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $plainPassword = $data['password'] ?? null;
        $pseudo = $data['pseudo'] ?? null;
        $postalCode = $data['postalCode'] ?? null;

        if (!$email || !$plainPassword || !$pseudo || !$postalCode) {
            return new JsonResponse(['message' => 'Champs requis: email, password, pseudo, postalCode'], 400);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setPostalCode($postalCode);
        $user->setRoles([]); // ROLE_USER ajouté automatiquement via getRoles()

        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'Utilisateur créé'], 201);
    }
}
