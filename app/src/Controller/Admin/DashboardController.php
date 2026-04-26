<?php

declare(strict_types=1);

namespace App\Controller\Admin;

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
        $url = $this->adminUrlGenerator->setController(UserCrudController::class)->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Symfony BaaS — Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('BaaS');
        yield MenuItem::linkToUrl('API resources', 'fa fa-database', '/api/baas/resources');
        yield MenuItem::linkToUrl('Schema overview', 'fa fa-table', '/api/baas/schema');
        yield MenuItem::section('Users');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-user');
        yield MenuItem::section();
        yield MenuItem::linkToUrl('BaaS home', 'fa fa-arrow-left', '/');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out-alt');
    }
}
