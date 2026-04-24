<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\RestaurantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single restaurant configuration row (the CDC describes one establishment).
Capacity feeds the reservation availability check.

 *
 * @generated baas-codegen
 */
#[ORM\Entity(repositoryClass: RestaurantRepository::class)]
#[ORM\Table(name: 'restaurants')]
#[ApiResource(
    operations: [
        new GetCollection,
        new Get,
        new Post,
        new Patch,
    ],
    normalizationContext: ['groups' => ['restaurant:read']],
    denormalizationContext: ['groups' => ['restaurant:write']],
    paginationItemsPerPage: 10,
)]
class Restaurant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['default:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Assert\NotBlank]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private string $city = '';

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private ?string $phone = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    #[Assert\NotBlank]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private int $capacity = 40;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotBlank]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private int $serviceDurationMinutes = 120;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['restaurant:read', 'restaurant:write'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getServiceDurationMinutes(): int
    {
        return $this->serviceDurationMinutes;
    }

    public function setServiceDurationMinutes(int $serviceDurationMinutes): self
    {
        $this->serviceDurationMinutes = $serviceDurationMinutes;
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
}
