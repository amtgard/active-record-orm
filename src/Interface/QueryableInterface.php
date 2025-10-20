<?php

namespace Amtgard\ActiveRecordOrm\Interface;

interface QueryableInterface
{
    function query(string $sql): void;
	function execute(): int;

}