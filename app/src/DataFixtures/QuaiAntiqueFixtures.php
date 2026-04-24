<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Dish;
use App\Entity\Menu;
use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\ServiceHour;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * End-to-end seed for the Quai Antique sample: admin user, the restaurant,
 * its weekly service hours, a starter menu and a couple of reservations.
 *
 * Hand-written; not produced by the generator. The generated *Fixtures
 * classes only fire when models declare random `seeds.count`, which we
 * intentionally skip for relational data.
 */
final class QuaiAntiqueFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = (new User())
            ->setEmail('admin@quai-antique.test')
            ->setRoles(['ROLE_ADMIN'])
            ->setDisplayName('Hôte Quai Antique')
            ->setFirstName('Arnaud')
            ->setLastName('Michant')
            ->setDefaultGuests(2);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        $client = (new User())
            ->setEmail('client@quai-antique.test')
            ->setRoles(['ROLE_CLIENT'])
            ->setDisplayName('Camille Client')
            ->setFirstName('Camille')
            ->setLastName('Client')
            ->setDefaultGuests(2)
            ->setAllergies('arachides');
        $client->setPassword($this->hasher->hashPassword($client, 'client1234'));
        $manager->persist($client);

        $restaurant = (new Restaurant())
            ->setName('Le Quai Antique')
            ->setCity('Chambéry')
            ->setAddress('12 quai des Allobroges, 73000 Chambéry')
            ->setPhone('+33 4 79 00 00 00')
            ->setCapacity(40)
            ->setServiceDurationMinutes(120);
        $manager->persist($restaurant);

        // Tue..Sun (ISO 2..7), lunch 12:00-14:00 + dinner 19:00-22:00.
        foreach ([2, 3, 4, 5, 6, 7] as $weekday) {
            $lunch = (new ServiceHour())
                ->setWeekday($weekday)
                ->setServiceLabel('lunch')
                ->setOpensAt(new \DateTimeImmutable('12:00'))
                ->setClosesAt(new \DateTimeImmutable('14:00'))
                ->setMaxGuests(40);
            $lunch->setRestaurant($restaurant);
            $manager->persist($lunch);

            $dinner = (new ServiceHour())
                ->setWeekday($weekday)
                ->setServiceLabel('dinner')
                ->setOpensAt(new \DateTimeImmutable('19:00'))
                ->setClosesAt(new \DateTimeImmutable('22:00'))
                ->setMaxGuests(40);
            $dinner->setRestaurant($restaurant);
            $manager->persist($dinner);
        }

        $catEntries = (new Category())->setTitle('Entrées')->setPosition(1);
        $catPlats   = (new Category())->setTitle('Plats')->setPosition(2);
        $catDesserts = (new Category())->setTitle('Desserts')->setPosition(3);
        foreach ([$catEntries, $catPlats, $catDesserts] as $c) {
            $manager->persist($c);
        }

        foreach ([
            ['Salade de chèvre chaud', 'Mâche, croûtons, miel de Savoie', 1200, $catEntries],
            ['Tarte fine aux légumes', 'Légumes du marché, pesto maison', 1100, $catEntries],
            ['Filet de truite du lac', 'Beurre blanc, pommes vapeur', 2400, $catPlats],
            ['Diots polenta',           'Saucisses fumées, polenta crémeuse', 2100, $catPlats],
            ['Tarte aux myrtilles',     'Myrtilles fraîches, pâte sablée', 900,  $catDesserts],
        ] as [$title, $desc, $price, $cat]) {
            $dish = (new Dish())
                ->setTitle($title)
                ->setDescription($desc)
                ->setPriceCents($price);
            $dish->setCategory($cat);
            $manager->persist($dish);
        }

        foreach ([
            ['Menu du marché',     "Entrée + plat + dessert au choix selon arrivage.",          3200],
            ['Menu dégustation',   "5 services, accord mets-vins inclus.",                       6500],
        ] as [$title, $desc, $price]) {
            $manager->persist((new Menu())->setTitle($title)->setDescription($desc)->setPriceCents($price));
        }

        // Two demo reservations next week (lunch).
        $base = new \DateTimeImmutable('next tuesday 12:30');
        $r1 = (new Reservation())->setGuests(2)->setReservedAt($base)->setAllergies('aucune');
        $r1->setRestaurant($restaurant);
        $manager->persist($r1);

        $r2 = (new Reservation())->setGuests(4)->setReservedAt($base->modify('+1 day'))->setAllergies('arachides');
        $r2->setRestaurant($restaurant);
        $manager->persist($r2);

        $manager->flush();
    }
}
