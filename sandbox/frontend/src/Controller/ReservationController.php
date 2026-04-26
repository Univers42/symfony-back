<?php

declare(strict_types=1);

namespace App\Controller;

use App\Baas\BaasClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    private const SLOT_MINUTES = 15;
    private const MINUTES_MODIFIER = '+%d minutes';

    #[Route('/reservation', name: 'sandbox_reservation', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('reservation/index.html.twig');
    }

    #[Route('/reservation/availability', name: 'sandbox_reservation_availability', methods: ['GET'])]
    public function availability(Request $request, BaasClient $baas): JsonResponse
    {
        $dateRaw = (string) $request->query->get('date', 'today');
        $guests = max(1, (int) $request->query->get('guests', 2));
        $service = strtolower(trim((string) $request->query->get('service', '')));

        try {
            $date = new \DateTimeImmutable($dateRaw);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Invalid date.'], 422);
        }

        $restaurants = $baas->allRows('restaurants', limit: 1);
        $restaurant = $restaurants[0] ?? null;
        if ($restaurant === null) {
            return new JsonResponse(['error' => 'No restaurant configured in BaaS.'], 503);
        }

        $weekday = (int) $date->format('N');
        $where = ['weekday' => $weekday];
        if ($service !== '') {
            $where['serviceLabel'] = $service;
        }
        $hours = array_values(array_filter(
            $baas->allRows('service_hours', $where),
            fn (array $row): bool => (int) ($row['restaurantId'] ?? 0) === (int) ($restaurant['id'] ?? 0)
        ));

        $reservations = $baas->allRows('reservations', ['restaurantId' => (int) $restaurant['id']], 200);
        $duration = max(15, (int) ($restaurant['serviceDurationMinutes'] ?? 120));
        $slots = [];

        foreach ($hours as $hour) {
            $opens = $this->combine($date, (string) ($hour['opensAt'] ?? '00:00:00'));
            $closes = $this->combine($date, (string) ($hour['closesAt'] ?? '00:00:00'));
            $lastStart = $closes->modify(sprintf('-%d minutes', $duration));
            $cursor = $opens;

            while ($cursor <= $lastStart) {
                $slotEnd = $cursor->modify(sprintf(self::MINUTES_MODIFIER, $duration));
                $taken = $this->seatsTaken($reservations, $cursor, $slotEnd, $duration);
                $capacity = min((int) ($hour['maxGuests'] ?? 0), (int) ($restaurant['capacity'] ?? 0));
                $remaining = max(0, $capacity - $taken);

                $slots[] = [
                    'time' => $cursor->format('H:i'),
                    'service' => $hour['serviceLabel'] ?? null,
                    'available' => $remaining >= $guests,
                    'remaining' => $remaining,
                    'capacity' => $capacity,
                ];

                $cursor = $cursor->modify(sprintf(self::MINUTES_MODIFIER, self::SLOT_MINUTES));
            }
        }

        return new JsonResponse([
            'date' => $date->format('Y-m-d'),
            'guests' => $guests,
            'service' => $service ?: null,
            'restaurant' => $restaurant['name'] ?? 'Restaurant',
            'duration' => $duration,
            'slots' => $slots,
            'message' => $slots === [] ? 'Restaurant closed for this service on this date.' : null,
        ]);
    }

    private function combine(\DateTimeImmutable $date, string $time): \DateTimeImmutable
    {
        $parts = array_map('intval', explode(':', $time));

        return $date->setTime($parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
    }

    /** @param list<array<string,mixed>> $reservations */
    private function seatsTaken(array $reservations, \DateTimeImmutable $start, \DateTimeImmutable $end, int $duration): int
    {
        $taken = 0;
        foreach ($reservations as $reservation) {
            if (($reservation['status'] ?? 'confirmed') !== 'confirmed') {
                continue;
            }
            try {
                $reservedAt = new \DateTimeImmutable((string) ($reservation['reservedAt'] ?? ''));
            } catch (\Exception) {
                continue;
            }
            $reservationEnd = $reservedAt->modify(sprintf(self::MINUTES_MODIFIER, $duration));
            if ($reservedAt < $end && $reservationEnd > $start) {
                $taken += (int) ($reservation['guests'] ?? 0);
            }
        }

        return $taken;
    }
}
