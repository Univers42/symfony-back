<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Append-only mutation log. Written by an event subscriber (not yet wired)
whenever a postgres entity is created/updated/deleted.

 *
 * @generated baas-codegen
 */
#[ODM\Document(collection: 'audit_logs')]
#[ODM\Index(keys: ['occurredAt' => -1])]
#[ODM\Index(keys: ['entity' => 1, 'entityId' => 1])]
class AuditLog
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $actorEmail = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    private string $action = '';

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    private string $entity = '';

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    private string $entityId = '';

    #[ODM\Field(type: 'hash', nullable: true)]
    private ?array $payload = null;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getid(): ?string
    {
        return $this->id;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): self
    {
        $this->actorEmail = $actorEmail;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;
        return $this;
    }
}
