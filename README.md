# Ag Grid server side row model implementation for Symfony
This bundle implements the server side row model logic in accordance to [the Ag Grid documentation](https://www.ag-grid.com/javascript-data-grid/server-side-model/), exposing a single service that takes care of retrieving the rows automatically requested by Ag Grid, and returning a Response object with the expected format.
## Usage
Install the dependency with:
```
composer require tismaximo/ag-grid-row-model
```
Then, in your controller, create a new endpoint like so to use the implementation:
```
use AgGridRowModelBundle\Api\AgGridRowModelService
...
#[Route(path: '/ag-grid-rows', name: 'example_ag_grid_rows', methods: ['POST'])]
    public function rows(Request $request, ExampleRepository $repository, AgGridRowModelService $service)
	{/*{{{*/
		return $service->generateResponse($request, $repository);
	}/*}}}*/
```
Service contract:
```
public function generateResponse(Request $request, EntityRepository $repository): Response
```
