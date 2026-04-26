<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DishRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generated entity for Dish.
 *
 * @generated baas-codegen
 */
#[ORM\Entity(repositoryClass: DishRepository::class)]
#[ORM\Table(name: 'dishes')]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['default:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank]
    #[Groups(['dish:read', 'dish:write'])]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['dish:read', 'dish:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Assert\NotBlank]
    #[Groups(['dish:read', 'dish:write'])]
    private int $priceCents = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['dish:read', 'dish:write'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'dishes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): self
    {
        $this->priceCents = $priceCents;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }
}
