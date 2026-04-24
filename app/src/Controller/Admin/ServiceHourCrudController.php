<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ServiceHour;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class ServiceHourCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ServiceHour::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('restaurant'),
            ChoiceField::new('weekday')->setChoices([
                'Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4,
                'Vendredi' => 5, 'Samedi' => 6, 'Dimanche' => 7,
            ]),
            TextField::new('serviceLabel'),
            TimeField::new('opensAt'),
            TimeField::new('closesAt'),
            IntegerField::new('maxGuests'),
        ];
    }
}
