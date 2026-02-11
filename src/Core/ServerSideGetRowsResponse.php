<?php

namespace AgGridRowModelBundle\RowModel;

class ServerSideGetRowsResponse {
    public array $rows;
    public bool $success;
    public int $lastRow;
}