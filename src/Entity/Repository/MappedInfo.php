<?php

namespace Amtgard\ActiveRecordOrm\Entity\Repository;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\PostInit;

class MappedInfo {
    use Builder, Data;

    public string $annotation;
    public string $source;
    public string $destinationType;
    public ?string $backingReferencePk;
    public bool $nullable;

    #[PostInit]
    private function postInit() {
        if (is_subclass_of($this->destinationType, \DateTimeInterface::class, true)) {
            $this->destinationType = \DateTimeInterface::class;
        }
    }
}