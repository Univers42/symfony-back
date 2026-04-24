<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Restaurant;
use App\Entity\Reservation;
use App\Entity\ServiceHour;
use App\Repository\ReservationRepository;
use App\Repository\RestaurantRepository;
use App\Repository\ServiceHourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public availability probe used by the booking widget.
 *
 *  GET /api/reservations/availability?date=YYYY-MM-DD&guests=N&service=lunch|dinner
 *
 * Resolves the matching ServiceHour for the weekday + service window, then
 * walks the window in 15-minute steps and computes remaining seats for each
 * slot by summing concurrent confirmed reservations whose
 * [reservedAt, reservedAt + Restaurant.serviceDurationMinutes] window overlaps
 * the slot.
 */
class AvailabilityController extends AbstractController
{
    private const SLOT_MINUTES = 15;

    public function __construct(
        private readonly RestaurantRepository $restaurants,
        private readonly ServiceHourRepository $serviceHours,
        protected readonly ReservationRepository $reservations,
    ) {
    }

    #[Route('/api/reservations/availability', name: 'api_reservations_availability', methods: ['GET'], priority: 10)]
    public function index(Request $request): JsonResponse
    {
        $dateRaw    = (string) $request->query->get('date', '');
        $guests     = max(1, (int) $request->query->get('guests', 2));
        $serviceArg = strtolower(trim((string) $request->query->get('service', '')));

        try {
            $date = new \DateTimeImmutable($dateRaw === '' ? 'today' : $dateRaw);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Invalid "date" parameter (expected YYYY-MM-DD).'], 422);
        }

        $restaurant = $this->restaurants->findOneBy([]);
        if ($restaurant === null) {
            return new JsonResponse(['error' => 'No restaurant configured.'], 503);
        }

        $weekday = (int) $date->format('N'); // 1..7

        $hours = $this->matchingServiceHours($restaurant, $weekday, $serviceArg);
        if ($hours === []) {
            return new JsonResponse([
                'date'       => $date->format('Y-m-d'),
                'guests'     => $guests,
                'service'    => $serviceArg ?: null,
                'restaurant' => $restaurant->getName(),
                'slots'      => [],
                'message'    => 'Restaurant closed for this service on this date.',
            ]);
        }

        $duration = $restaurant->getServiceDurationMinutes();
        $slots = [];

        foreach ($hours as $sh) {
            $opens  = $this->combine($date, $sh->getOpensAt());
            $closes = $this->combine($date, $sh->getClosesAt());
            // Last seat-able start so the meal still finishes before service end.
            $lastStart = $closes->modify(sprintf('-%d minutes', $duration));
            if ($lastStart < $opens) {
                continue;
            }

            $cursor = $opens;
            while ($cursor <= $lastStart) {
                $slotStart = $cursor;
                $slotEnd   = $cursor->modify(sprintf('+%d minutes', $duration));
                $taken     = $this->seatsTaken($restaurant, $slotStart, $slotEnd);
                $cap       = min($sh->getMaxGuests(), $restaurant->getCapacity());
                $remaining = max(0, $cap - $taken);

                $slots[] = [
                    'time'      => $slotStart->format('H:i'),
                    'service'   => $sh->getServiceLabel(),
                    'available' => $remaining >= $guests,
                    'remaining' => $remaining,
                    'capacity'  => $cap,
                ];

                $cursor = $cursor->modify(sprintf('+%d minutes', self::SLOT_MINUTES));
            }
        }

        return new JsonResponse([
            'date'       => $date->format('Y-m-d'),
            'guests'     => $guests,
            'service'    => $serviceArg ?: null,
            'restaurant' => $restaurant->getName(),
            'duration'   => $duration,
            'slots'      => $slots,
        ]);
    }

    /** @return ServiceHour[] */
    private function matchingServiceHours(Restaurant $restaurant, int $weekday, string $serviceArg): array
    {
        $criteria = ['restaurant' => $restaurant, 'weekday' => $weekday];
        if ($serviceArg !== '') {
            $criteria['serviceLabel'] = $serviceArg;
        }

        return $this->serviceHours->findBy($criteria, ['opensAt' => 'ASC']);
    }

    private function combine(\DateTimeImmutable $date, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return $date->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            0,
        );
    }

    private function seatsTaken(Restaurant $restaurant, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return $this->countConcurrentSeats($restaurant, $start, $end);
    }

    /** Extracted so tests can override the DB call. */
    protected function countConcurrentSeats(Restaurant $restaurant, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $qb = $this->reservations->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.guests), 0) AS booked')
            ->where('r.restaurant = :restaurant')
            ->andWhere('r.status = :confirmed')
            ->andWhere('r.reservedAt < :end')
            ->andWhere('r.reservedAt > :startMinusDuration')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('end', $end)
            ->setParameter('startMinusDuration', $start->modify(sprintf('-%d minutes', $restaurant->getServiceDurationMinutes())));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
