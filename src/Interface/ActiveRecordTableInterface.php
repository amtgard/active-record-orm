<?php

namespace Amtgard\ActiveRecordOrm\Interface;

use Amtgard\ActiveRecordOrm\Query\OrderBy;

interface ActiveRecordTableInterface
{
    /**
     * Magic method to set field values
     */
    public function __set(string $name, $value): void;

    /**
     * Magic method to get field values
     */
    public function __get(string $name);

    /**
     * Clear the table state and reset to initial values
     */
    public function clear(): void;

    /**
     * Set the order by clause for queries
     */
    public function orderBy(string $fieldName, OrderBy $orderBy): void;

    /**
     * Select specific fields for queries
     */
    public function select(mixed $fieldNameOrSet): void;

    /**
     * Find records and return the count
     */
    public function find(): int;

    /**
     * Count records and return the count
     */
    public function count(string $countAlias = 'row_count'): int;

    /**
     * Set pagination parameters
     */
    public function page(int $size = 10, int $page = 0): self;

    /**
     * Set limit parameters for queries
     */
    public function limit(int $offset, ?int $rowCount = null): void;

    /**
     * Get the size of the result set
     */
    public function size(): int;

    /**
     * Move to the next record
     */
    public function next(): bool;

    /**
     * Check if there is an active record
     */
    public function hasActiveRecord(): bool;

}