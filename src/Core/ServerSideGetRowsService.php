<?php

namespace AgGridRowModel\Core;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class ServerSideGetRowsService {
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em) {
        $this->em = $em;
    }

    public function getData(string $entityClass, array $request): ServerSideGetRowsResponse {
        $request = new ServerSideGetRowsRequest($request);

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($entityClass, 'e');

        $this->applyFilters($qb, $request->filterModel ?? []);
        $this->applySorting($qb, $request->sortModel ?? []);
        $this->applyPagination($qb, $request);

        $rows = $qb->getQuery()->getArrayResult();
        $lastRow = $this->getTotalCount($entityClass, $request);

        $response = new ServerSideGetRowsResponse();

        $response->success = true;
        $response->rows = $rows;
        $response->lastRow = $lastRow;

        return $response;
    }

    private function applyPagination(QueryBuilder $qb, ServerSideGetRowsRequest $request): void {
        $start = $request->startRow ?? 0;
        $end = $request->endRow ?? 100;

        $qb->setFirstResult($start);
        $qb->setMaxResults($end - $start);
    }

    private function applySorting(QueryBuilder $qb, array $sortModel): void {
        foreach ($sortModel as $sort) {
            $field = $sort['colId'];
            $direction = strtoupper($sort['sort']) === 'DESC' ? 'DESC' : 'ASC';

            $qb->addOrderBy("e.$field", $direction);
        }
    }

    private function applyFilters(QueryBuilder $qb, array $filterModel): void {
        $i = 0;

        foreach ($filterModel as $field => $filter) {
            $param = 'param' . $i++;

            if (!isset($filter['filterType'])) {
                continue;
            }

            switch ($filter['filterType']) {

                case 'text':
                    $this->applyTextFilter($qb, $field, $filter, $param);
                    break;

                case 'number':
                    $this->applyNumberFilter($qb, $field, $filter, $param);
                    break;

                case 'date':
                    $this->applyDateFilter($qb, $field, $filter, $param);
                    break;
            }
        }
    }

    private function applyTextFilter(QueryBuilder $qb, string $field, array $filter, string $param): void {
        $value = $filter['filter'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                $qb->andWhere("e.$field = :$param")
                   ->setParameter($param, $value);
                break;

            case 'contains':
                $qb->andWhere("e.$field LIKE :$param")
                   ->setParameter($param, "%$value%");
                break;

            case 'startsWith':
                $qb->andWhere("e.$field LIKE :$param")
                   ->setParameter($param, "$value%");
                break;

            case 'endsWith':
                $qb->andWhere("e.$field LIKE :$param")
                   ->setParameter($param, "%$value");
                break;
        }
    }

    private function applyNumberFilter(QueryBuilder $qb, string $field, array $filter, string $param): void {
        $value = $filter['filter'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                $qb->andWhere("e.$field = :$param");
                break;

            case 'greaterThan':
                $qb->andWhere("e.$field > :$param");
                break;

            case 'lessThan':
                $qb->andWhere("e.$field < :$param");
                break;

            case 'greaterThanOrEqual':
                $qb->andWhere("e.$field >= :$param");
                break;

            case 'lessThanOrEqual':
                $qb->andWhere("e.$field <= :$param");
                break;
        }

        $qb->setParameter($param, $value);
    }

    private function applyDateFilter(QueryBuilder $qb, string $field, array $filter, string $param): void {
        $value = $filter['dateFrom'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                $qb->andWhere("e.$field = :$param");
                break;

            case 'greaterThan':
                $qb->andWhere("e.$field > :$param");
                break;

            case 'lessThan':
                $qb->andWhere("e.$field < :$param");
                break;
        }

        $qb->setParameter($param, new \DateTime($value));
    }

    private function getTotalCount(string $entityClass, ServerSideGetRowsRequest $request): int {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(e)')
            ->from($entityClass, 'e');

        $this->applyFilters($qb, $request->filterModel ?? []);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}