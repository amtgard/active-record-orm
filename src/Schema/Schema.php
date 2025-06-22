<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use Optional\Optional;

class Schema
{
    use Builder;
    use Getter;

    /** @var FieldDefinition[] */
    protected array $fields = [];

    public function hasField(string $name): bool {
        return Optional::ofNullable($this->getField($name))
            ->map(fn($f) => true)
            ->orElseThrow(new \InvalidArgumentException("Field `$name` is not a recognized field in the schema for table `$this->tableName`. Perhaps you meant `" . $this->suggestField($name) . "`?"));
    }

    public function suggestField(string $needle): string {
        $fields = array_map(fn($f) => $f->getName(), $this->fields);
        $fuzz = new Fuzz();
        $process = new Process($fuzz);
        $result = $process->extractOne($needle, $fields);
        return $result[0];
    }

    public function getField($name) {
        return $this->fields[$name] ?? null;
    }

    public function getFields(): array {
        return $this->fields;
    }

}