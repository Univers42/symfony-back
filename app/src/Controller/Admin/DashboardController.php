<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\DishCrudController;
use App\Controller\Admin\MenuCrudController;
use App\Controller\Admin\ReservationCrudController;
use App\Controller\Admin\RestaurantCrudController;
use App\Controller\Admin\ServiceHourCrudController;
use App\Controller\Admin\UserCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public function index(): Response
    {
        $url = $this->adminUrlGenerator->setController(RestaurantCrudController::class)->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Quai Antique — Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Restaurant');
        yield MenuItem::linkTo(RestaurantCrudController::class, 'Restaurants', 'fa fa-store');
        yield MenuItem::linkTo(ServiceHourCrudController::class, 'Service Hours', 'fa fa-clock');
        yield MenuItem::section('Menu');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Categories', 'fa fa-tags');
        yield MenuItem::linkTo(DishCrudController::class, 'Dishes', 'fa fa-utensils');
        yield MenuItem::linkTo(MenuCrudController::class, 'Menus', 'fa fa-book');
        yield MenuItem::section('Réservations');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Reservations', 'fa fa-calendar');
        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-user');
        yield MenuItem::section();
        yield MenuItem::linkToUrl('Site public', 'fa fa-arrow-left', '/');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out-alt');
    }
}
