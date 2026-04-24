<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\AvailabilityController;
use App\Entity\Restaurant;
use App\Entity\ServiceHour;
use App\Repository\ReservationRepository;
use App\Repository\RestaurantRepository;
use App\Repository\ServiceHourRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pure-PHP unit test for AvailabilityController. Repositories are mocked and
 * the controller is subclassed so the SQL count is replaced with a fixed
 * value — isolating the slot algorithm from Doctrine.
 */
final class AvailabilityControllerTest extends TestCase
{
    public function testReturnsClosedMessageWhenNoServiceHourMatches(): void
    {
        $controller = $this->makeController($this->makeRestaurant(40, 120), [], 0);
        $resp = $controller->index(new Request(['date' => '2026-04-27', 'guests' => 2, 'service' => 'lunch']));

        self::assertSame(200, $resp->getStatusCode());
        $payload = $this->decode($resp);
        self::assertSame([], $payload['slots']);
        self::assertStringContainsString('closed', $payload['message']);
    }

    public function testGenerates15MinuteSlotsWithinServiceWindow(): void
    {
        $hour = $this->makeServiceHour(2 /* Tuesday */, 'lunch', '12:00', '14:00', 40);
        $controller = $this->makeController($this->makeRestaurant(40, 120), [$hour], 0);

        // 2026-04-28 is a Tuesday.
        $resp = $controller->index(new Request(['date' => '2026-04-28', 'guests' => 2, 'service' => 'lunch']));
        $payload = $this->decode($resp);

        // 12:00 is the only seat-able start (12:00 + 120m = 14:00 == close).
        self::assertSame(['12:00'], array_map(fn ($s) => $s['time'], $payload['slots']));
        self::assertSame('lunch', $payload['slots'][0]['service']);
        self::assertSame(40, $payload['slots'][0]['remaining']);
        self::assertTrue($payload['slots'][0]['available']);
    }

    public function testCapacityIsReducedByOverlappingReservations(): void
    {
        $hour = $this->makeServiceHour(2, 'lunch', '12:00', '14:00', 40);
        $controller = $this->makeController($this->makeRestaurant(40, 120), [$hour], 35);

        $resp = $controller->index(new Request(['date' => '2026-04-28', 'guests' => 6, 'service' => 'lunch']));
        $payload = $this->decode($resp);

        self::assertSame(40 - 35, $payload['slots'][0]['remaining']);
        self::assertFalse($payload['slots'][0]['available'], 'Should not seat 6 when only 5 free.');
    }

    public function testInvalidDateReturns422(): void
    {
        $controller = $this->makeController($this->makeRestaurant(40, 120), [], 0);
        $resp = $controller->index(new Request(['date' => 'nonsense']));
        self::assertSame(422, $resp->getStatusCode());
    }

    public function testLongerServiceWindowProducesMultipleSlots(): void
    {
        // Dinner 19:00-23:00, 120m duration → seat-able starts every 15m from 19:00 to 21:00.
        $hour = $this->makeServiceHour(2, 'dinner', '19:00', '23:00', 40);
        $controller = $this->makeController($this->makeRestaurant(40, 120), [$hour], 0);

        $resp = $controller->index(new Request(['date' => '2026-04-28', 'guests' => 2, 'service' => 'dinner']));
        $payload = $this->decode($resp);

        $times = array_map(fn ($s) => $s['time'], $payload['slots']);
        self::assertContains('19:00', $times);
        self::assertContains('20:45', $times);
        self::assertContains('21:00', $times);
        self::assertNotContains('21:15', $times, 'After 21:00 the meal would not finish before 23:00 close.');
    }

    private function makeRestaurant(int $capacity, int $duration): Restaurant
    {
        $r = new Restaurant();
        $r->setName('Test')->setCity('Chambéry')->setCapacity($capacity)->setServiceDurationMinutes($duration);

        return $r;
    }

    private function makeServiceHour(int $weekday, string $label, string $opens, string $closes, int $maxGuests): ServiceHour
    {
        $sh = new ServiceHour();
        $sh->setWeekday($weekday)
           ->setServiceLabel($label)
           ->setOpensAt(new \DateTimeImmutable($opens))
           ->setClosesAt(new \DateTimeImmutable($closes))
           ->setMaxGuests($maxGuests);

        return $sh;
    }

    private function makeController(Restaurant $restaurant, array $serviceHours, int $bookedSeats): AvailabilityController
    {
        $restaurants = $this->createMock(RestaurantRepository::class);
        $restaurants->method('findOneBy')->willReturn($restaurant);

        $hours = $this->createMock(ServiceHourRepository::class);
        $hours->method('findBy')->willReturn($serviceHours);

        $reservations = $this->createMock(ReservationRepository::class);

        return new class($restaurants, $hours, $reservations, $bookedSeats) extends AvailabilityController {
            public function __construct(
                RestaurantRepository $r,
                ServiceHourRepository $sh,
                ReservationRepository $res,
                private readonly int $stubbedBooked,
            ) {
                parent::__construct($r, $sh, $res);
            }

            protected function countConcurrentSeats(Restaurant $restaurant, \DateTimeImmutable $start, \DateTimeImmutable $end): int
            {
                return $this->stubbedBooked;
            }
        };
    }

    private function decode(JsonResponse $r): array
    {
        return json_decode((string) $r->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
