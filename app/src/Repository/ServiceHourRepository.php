<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ServiceHour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceHour>
 *
 * @generated baas-codegen
 */
class ServiceHourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceHour::class);
    }
}
