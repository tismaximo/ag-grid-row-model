<?php

namespace AgGridRowModel\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Response;
use AgGridRowModel\Core\ServerSideGetRowsService;

class AgGridRowModelService {
    function __construct(
        private ServerSideGetRowsService $rowsService,
        private SerializerInterface $serializer
    ){}

    public function generateResponse(Request $request, EntityRepository $repository): Response {
        $body = $request->toArray();
        $response = $this->rowsService->getData($repository->getClassName(), $body);

        $jsonResponse = $this->serializer->serialize($response, 'json');
        return new Response($jsonResponse);
    }  
}