<?php

namespace App\Repository;

use App\Entity\Note;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    public function findWithFilters(?string $search, ?string $sort, string $order): array
    {
        $qb = $this->createQueryBuilder('n');

        if ($search) {
            $qb->andWhere('n.title LIKE :search OR n.content LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $allowedSortFields = ['createdAt', 'title', 'id'];
        if (in_array($sort, $allowedSortFields)) {
            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
            $qb->orderBy("n.$sort", $order);
        } else {
            $qb->orderBy('n.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}