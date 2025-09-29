<?php

namespace Amtgard\ActiveRecordOrm\Interface;

use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\ResultSet;

interface TableInterface extends TableQueryInterface
{

    /**
     * Get the result set
     */
    public function getResultSet(): ResultSet;

    /**
     * Save the current record (insert or update)
     */
    public function save(): void;

    /**
     * Delete the current record
     */
    public function delete(): void;

    /**
     * Magic method to handle dynamic method calls for operations
     */
    public function __call(string $name, array $arguments): void;

    /**
     * Set a field operation
     */
    public function operation(string $name, Operation $operation, $value): void;
} 