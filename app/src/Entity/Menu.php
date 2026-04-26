<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generated entity for Menu.
 *
 * @generated baas-codegen
 */
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\Table(name: 'menus')]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['default:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank]
    #[Groups(['menu:read', 'menu:write'])]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Assert\NotBlank]
    #[Groups(['menu:read', 'menu:write'])]
    private int $priceCents = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['menu:read', 'menu:write'])]
    private \DateTimeImmutable $createdAt;

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
}
