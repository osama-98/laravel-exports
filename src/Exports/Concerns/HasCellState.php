<?php

namespace Osama\LaravelExports\Exports\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

trait HasCellState
{
    protected mixed $defaultState = null;

    protected mixed $getStateUsing = null;

    protected string|Closure|null $separator = null;

    protected bool|Closure $isDistinctList = false;

    public function distinctList(bool|Closure $condition = true): static
    {
        $this->isDistinctList = $condition;

        return $this;
    }

    public function getStateUsing(mixed $callback): static
    {
        $this->getStateUsing = $callback;

        return $this;
    }

    public function state(mixed $state): static
    {
        $this->getStateUsing($state);

        return $this;
    }

    public function default(mixed $state): static
    {
        $this->defaultState = $state;

        return $this;
    }

    public function separator(string|Closure|null $separator = ','): static
    {
        $this->separator = $separator;

        return $this;
    }

    public function isDistinctList(): bool
    {
        return (bool) $this->evaluate($this->isDistinctList);
    }

    public function getDefaultState(): mixed
    {
        return $this->evaluate($this->defaultState);
    }

    public function getSeparator(): ?string
    {
        return $this->evaluate($this->separator);
    }

    public function hasRelationship(Model $record): bool
    {
        $name = $this->getName();

        if (! str($name)->contains('.')) {
            return false;
        }

        if ($record->hasAttribute((string) str($name)->before('.'))) {
            return false;
        }

        return $record->isRelation((string) str($name)->before('.'));
    }

    public function getRelationship(Model $record, ?string $relationshipName = null): ?Relation
    {
        if (isset($relationshipName)) {
            $nameParts = explode('.', $relationshipName);
        } else {
            $name = $this->getName();

            if (! str($name)->contains('.')) {
                return null;
            }

            $nameParts = explode('.', $name);
            array_pop($nameParts);
        }

        $relationship = null;

        foreach ($nameParts as $namePart) {
            if ($record->hasAttribute($namePart)) {
                break;
            }

            if (! $record->isRelation($namePart)) {
                break;
            }

            $relationship = $record->{$namePart}();
            $record = $relationship->getRelated();
        }

        return $relationship;
    }

    public function hasMultipleRelationship(Model $record): bool
    {
        $relationships = explode('.', $this->getRelationshipName($record) ?? '');

        while (count($relationships)) {
            $currentRelationshipName = array_shift($relationships);

            $currentRelationshipValue = $record->getRelationValue($currentRelationshipName);

            if ($currentRelationshipValue instanceof Collection) {
                return true;
            }

            if (! $currentRelationshipValue instanceof Model) {
                break;
            }

            if (! count($relationships)) {
                break;
            }

            $record = $currentRelationshipValue;
        }

        return false;
    }

    /**
     * @param  array<string> | null  $relationships
     * @return array<Model>
     */
    public function getRelationshipResults(Model $record, ?array $relationships = null): array
    {
        $results = [];

        $relationships ??= explode('.', $this->getRelationshipName($record) ?? '');

        while (count($relationships)) {
            $currentRelationshipName = array_shift($relationships);

            $currentRelationshipValue = $record->getRelationValue($currentRelationshipName);

            if ($currentRelationshipValue instanceof Collection) {
                if (! count($relationships)) {
                    $results = [
                        ...$results,
                        ...$currentRelationshipValue->all(),
                    ];

                    continue;
                }

                foreach ($currentRelationshipValue as $valueRecord) {
                    $results = [
                        ...$results,
                        ...$this->getRelationshipResults(
                            $valueRecord,
                            $relationships,
                        ),
                    ];
                }

                break;
            }

            if (! $currentRelationshipValue instanceof Model) {
                break;
            }

            if (! count($relationships)) {
                $results[] = $currentRelationshipValue;

                break;
            }

            $record = $currentRelationshipValue;
        }

        return $results;
    }

    public function getAttributeName(Model $record): string
    {
        $name = $this->getName();

        if (! str($name)->contains('.')) {
            return $name;
        }

        $nameParts = explode('.', $name);
        $lastPart = array_pop($nameParts);

        foreach ($nameParts as $namePart) {
            if ($record->hasAttribute($namePart)) {
                break;
            }

            if (! $record->isRelation($namePart)) {
                break;
            }

            array_shift($nameParts);
            $record = $record->{$namePart}()->getRelated();
        }

        return Arr::first([...$nameParts, $lastPart]);
    }

    public function getFullAttributeName(Model $record): string
    {
        $name = $this->getName();

        if (! str($name)->contains('.')) {
            return $name;
        }

        $nameParts = explode('.', $name);
        $lastPart = array_pop($nameParts);

        foreach ($nameParts as $namePart) {
            if ($record->hasAttribute($namePart)) {
                break;
            }

            if (! $record->isRelation($namePart)) {
                break;
            }

            array_shift($nameParts);
            $record = $record->{$namePart}()->getRelated();
        }

        return implode('.', [...$nameParts, $lastPart]);
    }

    public function getRelationshipName(Model $record): ?string
    {
        $name = $this->getName();

        if (! str($name)->contains('.')) {
            return null;
        }

        $nameParts = explode('.', $name);
        array_pop($nameParts);

        $relationshipParts = [];

        foreach ($nameParts as $namePart) {
            if ($record->hasAttribute($namePart)) {
                break;
            }

            if (! $record->isRelation($namePart)) {
                break;
            }

            $relationshipParts[] = $namePart;
            $record = $record->{$namePart}()->getRelated();
        }

        return implode('.', $relationshipParts) ?: null;
    }
}
