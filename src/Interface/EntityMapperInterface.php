<?php

namespace Amtgard\ActiveRecordOrm\Interface;

interface EntityMapperInterface
{
    function getEntity(): ?EntityInterface;
	function fetch($primaryKeyValue = null): ?EntityInterface;
	function fetchBy(string $field, $value): ?EntityInterface;
	function persist(EntityInterface $entity): EntityInterface;
    function getTable(): TableInterface;
    function getChanges(): array;
}