<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('restaurant'),
            DateTimeField::new('reservedAt'),
            IntegerField::new('guests'),
            ChoiceField::new('status')->setChoices([
                'Pending'   => 'pending',
                'Confirmed' => 'confirmed',
                'Cancelled' => 'cancelled',
            ]),
            TextareaField::new('allergies')->hideOnIndex(),
        ];
    }
}
