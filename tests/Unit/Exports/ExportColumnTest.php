<?php

use Osama\LaravelExports\Exports\ExportColumn;
use Osama\LaravelExports\Exports\Exporter;
use Osama\LaravelExports\Exports\Models\Export;

beforeEach(function () {
    $this->exporter = Mockery::mock(Exporter::class);
    $this->export = Mockery::mock(Export::class);
    $this->exporter->shouldReceive('getRecord')->andReturn(null)->byDefault();
    $this->exporter->shouldReceive('getOptions')->andReturn([])->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('ExportColumn', function () {
    it('can be created with make method', function () {
        $column = ExportColumn::make('name');

        expect($column)->toBeInstanceOf(ExportColumn::class)
            ->and($column->getName())->toBe('name');
    });

    it('throws exception when name is blank', function () {
        expect(fn () => ExportColumn::make(''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('can set label', function () {
        $column = ExportColumn::make('name')
            ->label('Full Name');

        expect($column->getLabel())->toBe('Full Name');
    });

    it('generates label from name if not set', function () {
        $column = ExportColumn::make('first_name');

        expect($column->getLabel())->toBe('First name');
    });

    it('can set enabled by default', function () {
        $column = ExportColumn::make('name')
            ->enabledByDefault(false);

        expect($column->isEnabledByDefault())->toBeFalse();
    });

    it('can set default state', function () {
        $column = ExportColumn::make('name')
            ->default('N/A');

        expect($column->getDefaultState())->toBe('N/A');
    });

    it('can set state using callback', function () {
        $column = ExportColumn::make('name')
            ->getStateUsing(fn ($record) => strtoupper($record->name));

        $record = (object) ['name' => 'john'];
        $column->exporter($this->exporter);
        $this->exporter->shouldReceive('getRecord')->andReturn($record);

        expect($column->getState())->toBe('JOHN');
    });

    it('can format state with limit', function () {
        $column = ExportColumn::make('description')
            ->limit(10);

        $column->exporter($this->exporter);
        $this->exporter->shouldReceive('getRecord')->andReturn((object) ['description' => 'This is a very long description']);

        $formatted = $column->getFormattedState();

        expect($formatted)->toContain('...')
            ->and(strlen($formatted))->toBeLessThanOrEqual(13); // 10 + '...'
    });

    it('can use separator for string values', function () {
        $column = ExportColumn::make('tags')
            ->separator(',');

        $column->exporter($this->exporter);
        $this->exporter->shouldReceive('getRecord')->andReturn((object) ['tags' => 'tag1,tag2,tag3']);

        expect($column->getState())->toBe(['tag1', 'tag2', 'tag3']);
    });

    it('can set prefix and suffix', function () {
        $column = ExportColumn::make('price')
            ->prefix('$')
            ->suffix('.00');

        $column->exporter($this->exporter);
        $this->exporter->shouldReceive('getRecord')->andReturn(['price' => '100']);

        expect($column->getFormattedState())->toBe('$100.00');
    });

    it('can aggregate relationships with counts', function () {
        $column = ExportColumn::make('posts_count')
            ->counts('posts');

        expect($column->getRelationshipsToCount())->toBe('posts');
    });

    it('can aggregate relationships with avg', function () {
        $column = ExportColumn::make('avg_rating')
            ->avg('reviews', 'rating');

        expect($column->getRelationshipToAvg())->toBe('reviews')
            ->and($column->getColumnToAvg())->toBe('rating');
    });

    it('can aggregate relationships with sum', function () {
        $column = ExportColumn::make('total_amount')
            ->sum('orders', 'amount');

        expect($column->getRelationshipToSum())->toBe('orders')
            ->and($column->getColumnToSum())->toBe('amount');
    });
});
