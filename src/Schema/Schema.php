<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Utility\Constants;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use Optional\Optional;

class Schema implements \JsonSerializable
{
    use Builder;
    use Getter;

    /** @var FieldDefinition[] */
    protected array $fields = [];

    protected function getSuggestionMesssage(string $name, string $suggestion): string {
        return sprintf(Constants::$SCHEMA_FIELD_MISS_ERROR, $name, $suggestion);
    }

    public function hasField(string $name): bool {
        return Optional::ofNullable($this->getField($name))
            ->map(fn($f) => true)
            ->orElseThrow(new \InvalidArgumentException($this->getSuggestionMesssage($name, $this->suggestField($name))));
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

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}