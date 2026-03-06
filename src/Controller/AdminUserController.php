<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', name: 'api_admin_')]
final class AdminUserController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/users', name: 'users_list', methods: ['GET'])]
    public function listUsers(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findBy([], ['id' => 'DESC']);

        // IMPORTANT : ne jamais renvoyer le password (hash) !
        $data = array_map(static function ($user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'pseudo' => method_exists($user, 'getPseudo') ? $user->getPseudo() : null,
                'postalCode' => method_exists($user, 'getPostalCode') ? $user->getPostalCode() : null,
                'city' => method_exists($user, 'getCity') ? $user->getCity() : null,
                'createdAt' => method_exists($user, 'getCreatedAt') && $user->getCreatedAt()
                    ? $user->getCreatedAt()->format('Y-m-d H:i:s')
                    : null,
            ];
        }, $users);

        return $this->json($data);
    }
}
