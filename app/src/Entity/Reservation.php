<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Booked table slot. The reservation availability endpoint enforces the
per-service capacity defined on ServiceHour.maxGuests / Restaurant.capacity.

 *
 * @generated baas-codegen
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\Index(name: 'idx_reservation_reserved_at', columns: ['reserved_at'])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['default:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 50)]
    #[Assert\NotBlank]
    #[Groups(['reservation:read', 'reservation:write'])]
    private int $guests = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['reservation:read', 'reservation:write'])]
    private \DateTimeImmutable $reservedAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['reservation:read', 'reservation:write'])]
    private ?string $allergies = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Groups(['reservation:read', 'reservation:write'])]
    private string $status = 'confirmed';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['reservation:read', 'reservation:write'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Restaurant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Restaurant $restaurant = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGuests(): int
    {
        return $this->guests;
    }

    public function setGuests(int $guests): self
    {
        $this->guests = $guests;
        return $this;
    }

    public function getReservedAt(): \DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function setReservedAt(\DateTimeImmutable $reservedAt): self
    {
        $this->reservedAt = $reservedAt;
        return $this;
    }

    public function getAllergies(): ?string
    {
        return $this->allergies;
    }

    public function setAllergies(?string $allergies): self
    {
        $this->allergies = $allergies;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function setRestaurant(?Restaurant $restaurant): self
    {
        $this->restaurant = $restaurant;
        return $this;
    }
}
