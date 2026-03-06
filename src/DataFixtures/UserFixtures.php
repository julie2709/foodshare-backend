<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 🔹 ADMIN
        $admin = new User();
        $admin->setEmail('admin@foodshare.com');
        $admin->setPseudo('Admin');
        $admin->setPostalCode('69001');
        $admin->setRoles(['ROLE_ADMIN']);

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'Admin123!');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);

        // 🔹 USER normal
        $user = new User();
        $user->setEmail('user2@foodshare.com');
        $user->setPseudo('User2Test');
        $user->setPostalCode('69002');
        $user->setRoles([]); // ROLE_USER sera ajouté automatiquement

        $hashedPasswordUser = $this->passwordHasher->hashPassword($user, 'User123!');
        $user->setPassword($hashedPasswordUser);

        $manager->persist($user);

        $manager->flush();

         // 🔹 USER normal
        $user = new User();
        $user->setEmail('user3@foodshare.com');
        $user->setPseudo('User3Test');
        $user->setPostalCode('69002');
        $user->setRoles([]); // ROLE_USER sera ajouté automatiquement

        $hashedPasswordUser = $this->passwordHasher->hashPassword($user, 'User123!');
        $user->setPassword($hashedPasswordUser);

        $manager->persist($user);

        $manager->flush();
    }
}