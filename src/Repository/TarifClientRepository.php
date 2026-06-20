<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\TarifClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TarifClient> */
class TarifClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifClient::class);
    }

    public function findForClient(Client $client): ?TarifClient
    {
        return $this->findOneBy(['client' => $client]);
    }

    /** @return TarifClient[] indexed by client_id */
    public function indexedByClientId(): array
    {
        $result = [];
        foreach ($this->findAll() as $t) {
            $result[$t->getClient()->getId()] = $t;
        }
        return $result;
    }
}