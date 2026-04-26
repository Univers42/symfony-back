<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ServiceHourRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-weekday service window (lunch or dinner). The CDC fixes Tue->Sun
opening; weekday is stored as ISO 1..7 (1=Mon, 7=Sun).

 *
 * @generated baas-codegen
 */
#[ORM\Entity(repositoryClass: ServiceHourRepository::class)]
#[ORM\Table(name: 'service_hours')]
#[ORM\Index(name: 'idx_service_weekday', columns: ['weekday', 'service_label'])]
class ServiceHour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['default:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 7)]
    #[Assert\NotBlank]
    #[Groups(['service_hour:read', 'service_hour:write'])]
    private int $weekday = 0;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Groups(['service_hour:read', 'service_hour:write'])]
    private string $serviceLabel = '';

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Groups(['service_hour:read', 'service_hour:write'])]
    private \DateTimeImmutable $opensAt;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Groups(['service_hour:read', 'service_hour:write'])]
    private \DateTimeImmutable $closesAt;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Groups(['service_hour:read', 'service_hour:write'])]
    private int $maxGuests = 40;

    #[ORM\ManyToOne(targetEntity: Restaurant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Restaurant $restaurant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): self
    {
        $this->weekday = $weekday;
        return $this;
    }

    public function getServiceLabel(): string
    {
        return $this->serviceLabel;
    }

    public function setServiceLabel(string $serviceLabel): self
    {
        $this->serviceLabel = $serviceLabel;
        return $this;
    }

    public function getOpensAt(): \DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(\DateTimeImmutable $opensAt): self
    {
        $this->opensAt = $opensAt;
        return $this;
    }

    public function getClosesAt(): \DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(\DateTimeImmutable $closesAt): self
    {
        $this->closesAt = $closesAt;
        return $this;
    }

    public function getMaxGuests(): int
    {
        return $this->maxGuests;
    }

    public function setMaxGuests(int $maxGuests): self
    {
        $this->maxGuests = $maxGuests;
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
