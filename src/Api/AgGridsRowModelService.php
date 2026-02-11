<?php

namespace AgGridRowModelBundle\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Response;
use AgGridRowModelBundle\Core\ServerSideGetRowsService;

class AgGridsRowModelService {
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