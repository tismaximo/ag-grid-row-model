<?php

namespace AgGridRowModel\Core;

class ServerSideGetRowsRequest {
    public int $startRow;
    public int $endRow;
    public array $rowGroupCols;
    public array $valueCols;
    public array $pivotCols;
    public bool $pivotMode;
    public array $groupKeys;
    public array $filterModel;
    public array $sortModel;

    public function __construct($object = null) {
        if ($object) {
            foreach ($object as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->$property = $value;
                }
                else {
                    throw new \Exception("Invalid request format");
                }
            }
        }
    }
}