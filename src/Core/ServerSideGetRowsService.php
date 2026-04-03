<?php

namespace AgGridRowModel\Core;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class ServerSideGetRowsService {
    public function __construct(
        private EntityManagerInterface $em, 
        private array $is = [],
        private int $i = 0
    ) {}

    public function getData(string $entityClass, array $request, QueryBuilder|null $qb = null): ServerSideGetRowsResponse {
        $qb = $this->buildQuery($entityClass, $request, $qb);
        $response = $this->getResponseFromQuery($entityClass, $qb, $request);
        return $response;
    }

    public function buildQuery(string $entityClass, array $request, QueryBuilder|null $qb): QueryBuilder {
        $request = new ServerSideGetRowsRequest($request);

        if (!$qb)
            $qb = $this->em->createQueryBuilder()
            ->select('main')
            ->from($entityClass, 'main');

        $this->applyFilters($qb, $request->filterModel ?? []);
        $this->i = 0;
        $this->applySorting($qb, $request->sortModel ?? []);
        $this->applyPagination($qb, $request);

        return $qb;
    }

    public function getResponseFromQuery(string $entityClass, QueryBuilder $qb, array $request): ServerSideGetRowsResponse {
        $request = new ServerSideGetRowsRequest($request);
        $rows = $qb->getQuery()->getArrayResult();
        $lastRow = $this->getTotalCount($entityClass, $request);

        $response = new ServerSideGetRowsResponse();

        $response->success = true;
        $response->rows = $rows;
        $response->lastRow = $lastRow;

        return $response;
    }

    private function applyPagination(QueryBuilder &$qb, ServerSideGetRowsRequest $request): void {
        $start = $request->startRow ?? 0;
        $end = $request->endRow ?? 100;

        $qb->setFirstResult($start);
        $qb->setMaxResults($end - $start);
    }

    private function applySorting(QueryBuilder &$qb, array $sortModel): void {
        foreach ($sortModel as $sort) {
            $field = $sort['colId'];
            $direction = strtoupper($sort['sort']) === 'DESC' ? 'DESC' : 'ASC';

            $qb->addOrderBy("main.$field", $direction);
        }
    }

    private function applyFilters(QueryBuilder &$qb, array $filterModel): void {
        foreach ($filterModel as $field => $filter) {
            $param = 'param' . $this->i++;
            $this->is[] = $param;

            if (!isset($filter['filterType'])) {
                continue;
            }

            switch ($filter['filterType']) {

                case 'text':
                    if (isset($filter['conditions'])) {
                        foreach ($filter['conditions'] as $condition) {
                            $this->applyTextFilter($qb, $field, $condition, 'param'.$this->i++, $filter['operator']);
                        }
                    } else {
                        $this->applyTextFilter($qb, $field, $filter, $param);
                    }
                    break;

                case 'number':
                    if (isset($filter['conditions'])) {
                        foreach ($filter['conditions'] as $condition) {
                            $this->applyNumberFilter($qb, $field, $condition, 'param'.$this->i++, $filter['operator']);
                        }
                    } else {
                        $this->applyNumberFilter($qb, $field, $filter, $param);
                    }   
                    break;

                case 'date':
                    if (isset($filter['conditions'])) {
                        foreach ($filter['conditions'] as $condition) {
                            $this->applyDateFilter($qb, $field, $condition, 'param'.$this->i++, $filter['operator']);
                        }
                    } else {
                        $this->applyDateFilter($qb, $field, $filter, $param);
                    }
                    break;

                case 'multi':
                    foreach ($filter['filterModels'] as $subFilter) {
                        $this->applyFilters($qb, [$field => $subFilter]);
                    }
                    break;
            }
        }
    }

    private function applyTextFilter(QueryBuilder &$qb, string $field, array $filter, string $param, ?string $operator = null): void {
        $value = $filter['filter'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                if ($operator === 'OR') {
                    $qb->orWhere("main.$field = :$param");
                } else {
                    $qb->andWhere("main.$field = :$param");
                }
                $qb->setParameter($param, $value);
                break;

            case 'contains':
                if ($operator === 'OR') {
                    $qb->orWhere("main.$field LIKE :$param");
                } else {
                    $qb->andWhere("main.$field LIKE :$param");
                }
                $qb->setParameter($param, "%$value%");
                break;

            case 'startsWith':
                if ($operator === 'OR') {
                    $qb->orWhere("main.$field LIKE :$param");
                } else {
                    $qb->andWhere("main.$field LIKE :$param");
                }
                $qb->setParameter($param, "$value%");
                break;

            case 'endsWith':
                if ($operator === 'OR') {
                    $qb->orWhere("main.$field LIKE :$param");
                } else {
                    $qb->andWhere("main.$field LIKE :$param");
                }
                $qb->setParameter($param, "%$value");
                break;
        }
    }

    private function applyNumberFilter(QueryBuilder &$qb, string $field, array $filter, string $param): void {
        $value = $filter['filter'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                $qb->andWhere("main.$field = :$param");
                break;

            case 'greaterThan':
                $qb->andWhere("main.$field > :$param");
                break;

            case 'lessThan':
                $qb->andWhere("main.$field < :$param");
                break;

            case 'greaterThanOrEqual':
                $qb->andWhere("main.$field >= :$param");
                break;

            case 'lessThanOrEqual':
                $qb->andWhere("main.$field <= :$param");
                break;
        }

        $qb->setParameter($param, $value);
    }

    private function applyDateFilter(QueryBuilder &$qb, string $field, array $filter, string $param): void {
        $value = $filter['dateFrom'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                $qb->andWhere("main.$field = :$param");
                break;

            case 'greaterThan':
                $qb->andWhere("main.$field > :$param");
                break;

            case 'lessThan':
                $qb->andWhere("main.$field < :$param");
                break;
        }

        $qb->setParameter($param, new \DateTime($value));
    }

    private function getTotalCount(string $entityClass, ServerSideGetRowsRequest $request): int {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(main)')
            ->from($entityClass, 'main');

        $this->applyFilters($qb, $request->filterModel ?? []);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}