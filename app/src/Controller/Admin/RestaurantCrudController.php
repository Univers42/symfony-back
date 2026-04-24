<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Restaurant;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RestaurantCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Restaurant::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name'),
            TextField::new('city'),
            TextField::new('address'),
            TextField::new('phone'),
            IntegerField::new('capacity'),
            IntegerField::new('serviceDurationMinutes')->setLabel('Service duration (min)'),
        ];
    }
}
