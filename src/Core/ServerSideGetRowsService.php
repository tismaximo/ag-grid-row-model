<?php

namespace AgGridRowModel\Core;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class ServerSideGetRowsService {
    public function __construct(
        private EntityManagerInterface $em, 
        private int $i = 0,
        private array $selectAliases = []
    ) {}

    public function getData(string $entityClass, array $request, QueryBuilder|null $qb = null): ServerSideGetRowsResponse {
        $originalQuery = $qb;
        $qb = $this->buildQuery($entityClass, $request, $qb);
        $response = $this->getResponseFromQuery($entityClass, $qb, $request, $originalQuery);
        return $response;
    }

    public function buildQuery(string $entityClass, array $request, QueryBuilder|null $qb): QueryBuilder {
        $request = new ServerSideGetRowsRequest($request);

        if (!$qb)
            $qb = $this->em->createQueryBuilder()
            ->select('main')
            ->from($entityClass, 'main');

        $this->selectAliases = $this->getSelectAliases($qb);

        $this->applyFilters($qb, $request->filterModel ?? []);
        $this->i = 0;
        $this->applySorting($qb, $request->sortModel ?? []);
        $this->applyPagination($qb, $request);

        return $qb;
    }

    public function getResponseFromQuery(string $entityClass, QueryBuilder|null $qb, array $request, QueryBuilder|null $originalQuery = null): ServerSideGetRowsResponse {
        $request = new ServerSideGetRowsRequest($request);
        $rows = $qb->getQuery()->getArrayResult();
        $lastRow = $this->getTotalCount($entityClass, $request, $originalQuery);

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
            $expression = $this->selectAliases[$field] ?? "main.$field";

            $qb->addOrderBy($expression, $direction);
        }
    }

    private function applyFilters(QueryBuilder &$qb, array $filterModel): void {
        foreach ($filterModel as $field => $filter) {
            if (!isset($filter['filterType'])) {
                continue;
            }

            $expression = $this->selectAliases[$field] ?? "main.$field";

            switch ($filter['filterType']) {
                case 'text':
                    if (isset($filter['conditions'])) {
                        $group = ($filter['operator'] === 'OR') ? $qb->expr()->orX() : $qb->expr()->andX();
                        foreach ($filter['conditions'] as $condition) {
                            $param = 'param' . $this->i++;
                            $expr = $this->buildTextExpr($qb, $expression, $condition, $param);
                            $group->add($expr);
                        }
                        $qb->andWhere($group);
                    } else {
                        $param = 'param' . $this->i++;
                        $expr = $this->buildTextExpr($qb, $expression, $filter, $param);
                        $qb->andWhere($expr);
                    }
                    break;

                case 'number':
                    if (isset($filter['conditions'])) {
                        $group = ($filter['operator'] === 'OR') ? $qb->expr()->orX() : $qb->expr()->andX();
                        foreach ($filter['conditions'] as $condition) {
                            $param = 'param' . $this->i++;
                            $expr = $this->buildNumberExpr($qb, $expression, $condition, $param);
                            $group->add($expr);
                        }
                        $qb->andWhere($group);
                    } else {
                        $param = 'param' . $this->i++;
                        $expr = $this->buildNumberExpr($qb, $expression, $filter, $param);
                        $qb->andWhere($expr);
                    }
                    break;

                case 'date':
                    if (isset($filter['conditions'])) {
                        $group = ($filter['operator'] === 'OR') ? $qb->expr()->orX() : $qb->expr()->andX();
                        foreach ($filter['conditions'] as $condition) {
                            $param = 'param' . $this->i++;
                            $expr = $this->buildDateExpr($qb, $expression, $condition, $param);
                            $group->add($expr);
                        }
                        $qb->andWhere($group);
                    } else {
                        $param = 'param' . $this->i++;
                        $expr = $this->buildDateExpr($qb, $expression, $filter, $param);
                        $qb->andWhere($expr);
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

    private function buildTextExpr(QueryBuilder &$qb, string $expression, array $filter, string $param): string {
        $value = $filter['filter'];
        $type = $filter['type'];

        switch ($type) {
            case 'equals':
                $qb->setParameter($param, $value);
                return $qb->expr()->eq($expression, ':' . $param);

            case 'contains':
                $qb->setParameter($param, "%$value%");
                return $qb->expr()->like($expression, ':' . $param);

            case 'startsWith':
                $qb->setParameter($param, "$value%");
                return $qb->expr()->like($expression, ':' . $param);

            case 'endsWith':
                $qb->setParameter($param, "%$value");
                return $qb->expr()->like($expression, ':' . $param);
        }

        return '1=1';
    }

    private function buildNumberExpr(QueryBuilder &$qb, string $expression, array $filter, string $param): string {
        $value = $filter['filter'];
        $type = $filter['type'];

        $qb->setParameter($param, $value);

        switch ($type) {
            case 'equals':
                return $qb->expr()->eq($expression, ':' . $param);
            case 'greaterThan':
                return $qb->expr()->gt($expression, ':' . $param);
            case 'lessThan':
                return $qb->expr()->lt($expression, ':' . $param);
            case 'greaterThanOrEqual':
                return $qb->expr()->gte($expression, ':' . $param);
            case 'lessThanOrEqual':
                return $qb->expr()->lte($expression, ':' . $param);
        }

        return '1=1';
    }

    private function buildDateExpr(QueryBuilder &$qb, string $expression, array $filter, string $param): string {
        $value = $filter['dateFrom'];
        $type = $filter['type'];

        $qb->setParameter($param, new \DateTime($value));

        switch ($type) {
            case 'equals':
                return $qb->expr()->eq($expression, ':' . $param);
            case 'greaterThan':
                return $qb->expr()->gt($expression, ':' . $param);
            case 'lessThan':
                return $qb->expr()->lt($expression, ':' . $param);
        }

        return '1=1';
    }

    private function getTotalCount(string $entityClass, ServerSideGetRowsRequest $request, QueryBuilder|null $qb): int {
        if ($qb) {
            $this->applyFilters($qb, $request->filterModel ?? []);
            $qb->select('COUNT(main.id)');
        } else {
            $qb = $this->em->createQueryBuilder()
                ->select('COUNT(main.id)')
                ->from($entityClass, 'main');
            $this->applyFilters($qb, $request->filterModel ?? []);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function getSelectAliases( QueryBuilder $qb ) 
	{
		$selectAliases = [];

		foreach( $qb->getDqlParts()['select'] as $parts ) {
			foreach( $parts->getParts() as $part ) {
				$part = preg_replace('/ (as|AS|As) /', ';', $part);
				$aparts = explode( ';', $part );

				if ( count( $aparts ) < 2 ) {
					$aparts = explode( '.', $part );
					if ( count( $aparts ) < 2 )
						continue;
					else {
						$selectAliases[$aparts[1]] = $part;
					}
				} else {
					$selectAliases[$aparts[count($aparts)-1]] = $aparts[0];
				}
			}
		}

		return $selectAliases;
	}
}