<?php

declare(strict_types=1);

namespace App\Controller;

use App\Baas\BaasClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'sandbox_home', methods: ['GET'])]
    public function index(BaasClient $baas): Response
    {
        $restaurants = $baas->allRows('restaurants', limit: 1);
        $menus = $baas->allRows('menus', limit: 6);
        $dishes = $baas->allRows('dishes', limit: 12);

        return $this->render('home/index.html.twig', [
            'restaurant' => $restaurants[0] ?? null,
            'menus' => $menus,
            'dishes' => $dishes,
        ]);
    }
}
