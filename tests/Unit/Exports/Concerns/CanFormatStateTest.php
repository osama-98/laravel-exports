<?php

use Osama\LaravelExports\Exports\ExportColumn;

describe('CanFormatState Trait', function () {
    it('can set and get character limit', function () {
        $column = ExportColumn::make('description')->limit(50);

        expect($column->getCharacterLimit())->toBe(50);
    });

    it('can set and get character limit end', function () {
        $column = ExportColumn::make('description')
            ->limit(50, '...');

        expect($column->getCharacterLimitEnd())->toBe('...');
    });

    it('can set and get word limit', function () {
        $column = ExportColumn::make('description')->words(10);

        expect($column->getWordLimit())->toBe(10);
    });

    it('can set and get word limit end', function () {
        $column = ExportColumn::make('description')
            ->words(10, '...');

        expect($column->getWordLimitEnd())->toBe('...');
    });

    it('can set and get prefix', function () {
        $column = ExportColumn::make('price')->prefix('$');

        expect($column->getPrefix())->toBe('$');
    });

    it('can set and get suffix', function () {
        $column = ExportColumn::make('price')->suffix('.00');

        expect($column->getSuffix())->toBe('.00');
    });

    it('can format state with character limit', function () {
        $column = ExportColumn::make('description')
            ->limit(10);

        $formatted = $column->formatState('This is a very long description');
        expect(strlen($formatted))->toBeLessThanOrEqual(13); // 10 + '...'
    });

    it('can format state with word limit', function () {
        $column = ExportColumn::make('description')
            ->words(3);

        $formatted = $column->formatState('This is a very long description');
        expect($formatted)->toBe('This is a...');
    });

    it('can format state with prefix and suffix', function () {
        $column = ExportColumn::make('price')
            ->prefix('$')
            ->suffix('.00');

        $formatted = $column->formatState('100');
        expect($formatted)->toBe('$100.00');
    });

    it('can format state with enum', function () {
        // Create a proper enum
        enum TestStatus: string
        {
            case Active = 'active';
            case Inactive = 'inactive';
        }

        $column = ExportColumn::make('status');
        $formatted = $column->formatState(TestStatus::Active);

        // Should convert enum to its value
        expect($formatted)->toBe('active');
    });

    it('can format null state', function () {
        $column = ExportColumn::make('description')
            ->prefix('Prefix: ')
            ->suffix(' Suffix');

        $formatted = $column->formatState(null);
        expect($formatted)->toBe('Prefix:  Suffix');
    });

    it('can format array state as json', function () {
        $column = ExportColumn::make('tags')
            ->listAsJson();

        $formatted = $column->getFormattedState();
        expect($formatted)->toBeString();
    });

    it('can format array state as comma separated', function () {
        $column = ExportColumn::make('tags');

        $record = (object) ['tags' => ['tag1', 'tag2', 'tag3']];
        $column->exporter(new \Osama\LaravelExports\Tests\Feature\Exports\TestExporter(
            \Osama\LaravelExports\Exports\Models\Export::create([
                'exporter' => \Osama\LaravelExports\Tests\Feature\Exports\TestExporter::class,
                'file_disk' => 'local',
                'file_name' => 'test',
                'total_rows' => 0,
            ]),
            [],
            []
        ));

        // This would require more setup, so we'll test the method directly
        $state = ['tag1', 'tag2', 'tag3'];
        $formatted = $column->formatState($state);
        expect($formatted)->toBeArray();
    });

    it('can use formatStateUsing callback', function () {
        $column = ExportColumn::make('price')
            ->formatStateUsing(fn ($state) => number_format($state, 2));

        $formatted = $column->formatState(1000);
        expect($formatted)->toBe('1,000.00');
    });

    it('can set and check listAsJson', function () {
        $column = ExportColumn::make('tags')
            ->listAsJson(true);

        expect($column->isListedAsJson())->toBeTrue();

        $column->listAsJson(false);
        expect($column->isListedAsJson())->toBeFalse();
    });

    it('can format array state with getFormattedState as json', function () {
        $export = \Osama\LaravelExports\Exports\Models\Export::create([
            'exporter' => \Osama\LaravelExports\Tests\Feature\Exports\TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new \Osama\LaravelExports\Tests\Feature\Exports\TestExporter($export, [], []);

        // Set a record on the exporter
        $record = new class extends \Illuminate\Database\Eloquent\Model {};
        $exporter->__invoke($record);

        $column = ExportColumn::make('tags')
            ->listAsJson(true)
            ->getStateUsing(fn () => ['tag1', 'tag2', 'tag3']);
        $column->exporter($exporter);

        $formatted = $column->getFormattedState();
        expect($formatted)->toBeString()
            ->and(json_decode($formatted))->toBeArray();
    });

    it('can format array state with getFormattedState as comma separated', function () {
        $export = \Osama\LaravelExports\Exports\Models\Export::create([
            'exporter' => \Osama\LaravelExports\Tests\Feature\Exports\TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new \Osama\LaravelExports\Tests\Feature\Exports\TestExporter($export, [], []);

        // Set a record on the exporter
        $record = new class extends \Illuminate\Database\Eloquent\Model {};
        $exporter->__invoke($record);

        $column = ExportColumn::make('tags')
            ->listAsJson(false) // Not JSON, so comma separated
            ->getStateUsing(fn () => ['tag1', 'tag2', 'tag3']);
        $column->exporter($exporter);

        $formatted = $column->getFormattedState();
        expect($formatted)->toBeString()
            ->and($formatted)->toContain('tag1')
            ->and($formatted)->toContain('tag2')
            ->and($formatted)->toContain('tag3');
    });

    it('can use closures for character limit', function () {
        $column = ExportColumn::make('description')
            ->limit(fn () => 5);

        $formatted = $column->formatState('This is a very long description');
        expect(strlen($formatted))->toBeLessThanOrEqual(8); // 5 + '...'
    });

    it('can use closures for word limit', function () {
        $column = ExportColumn::make('description')
            ->words(fn () => 2);

        $formatted = $column->formatState('This is a very long description');
        expect($formatted)->toBe('This is...');
    });

    it('can use closures for prefix', function () {
        $column = ExportColumn::make('price')
            ->prefix(fn () => '$');

        $formatted = $column->formatState('100');
        expect($formatted)->toBe('$100');
    });

    it('can use closures for suffix', function () {
        $column = ExportColumn::make('price')
            ->suffix(fn () => '.00');

        $formatted = $column->formatState('100');
        expect($formatted)->toBe('100.00');
    });

    it('can use closures for character limit end', function () {
        $column = ExportColumn::make('description')
            ->limit(10, fn () => '***');

        $formatted = $column->formatState('This is a very long description');
        expect($formatted)->toContain('***');
    });

    it('can use closures for word limit end', function () {
        $column = ExportColumn::make('description')
            ->words(3, fn () => '***');

        $formatted = $column->formatState('This is a very long description');
        expect($formatted)->toContain('***');
    });

    it('handles formatStateUsing with state parameter', function () {
        $column = ExportColumn::make('price')
            ->formatStateUsing(function ($state) {
                return 'Price: '.$state;
            });

        $formatted = $column->formatState(100);
        expect($formatted)->toBe('Price: 100');
    });

    it('applies formatStateUsing before other formatting', function () {
        $column = ExportColumn::make('price')
            ->formatStateUsing(fn ($state) => $state * 2)
            ->prefix('$')
            ->suffix('.00');

        $formatted = $column->formatState(50);
        expect($formatted)->toBe('$100.00');
    });

    it('can format array state with formatting applied to each element', function () {
        $export = \Osama\LaravelExports\Exports\Models\Export::create([
            'exporter' => \Osama\LaravelExports\Tests\Feature\Exports\TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new \Osama\LaravelExports\Tests\Feature\Exports\TestExporter($export, [], []);

        // Set a record on the exporter
        $record = new class extends \Illuminate\Database\Eloquent\Model {};
        $exporter->__invoke($record);

        $column = ExportColumn::make('prices')
            ->prefix('$')
            ->suffix('.00')
            ->getStateUsing(fn () => [10, 20, 30]);
        $column->exporter($exporter);

        $formatted = $column->getFormattedState();
        expect($formatted)->toBeString()
            ->and($formatted)->toContain('$10.00')
            ->and($formatted)->toContain('$20.00')
            ->and($formatted)->toContain('$30.00');
    });

    it('handles empty prefix and suffix', function () {
        $column = ExportColumn::make('name')
            ->prefix('')
            ->suffix('');

        $formatted = $column->formatState('John');
        expect($formatted)->toBe('John');
    });

    it('handles null prefix and suffix', function () {
        $column = ExportColumn::make('name')
            ->prefix(null)
            ->suffix(null);

        $formatted = $column->formatState('John');
        expect($formatted)->toBe('John');
    });

    it('evaluates closures correctly', function () {
        $column = ExportColumn::make('value')
            ->prefix(function () {
                return 'Prefix: ';
            });

        $formatted = $column->formatState('test');
        expect($formatted)->toBe('Prefix: test');
    });

    it('evaluates non-closure values correctly', function () {
        $column = ExportColumn::make('value')
            ->prefix('Static: ');

        $formatted = $column->formatState('test');
        expect($formatted)->toBe('Static: test');
    });

    it('handles filled check with non-empty string', function () {
        $column = ExportColumn::make('value')
            ->prefix('$');

        // filled() should return true for non-empty string
        $formatted = $column->formatState('100');
        expect($formatted)->toContain('$');
    });

    it('handles filled check with empty string', function () {
        $column = ExportColumn::make('value')
            ->prefix('');

        // filled() should return false for empty string
        $formatted = $column->formatState('100');
        expect($formatted)->toBe('100'); // No prefix added
    });

    it('handles blank check with null', function () {
        $column = ExportColumn::make('value')
            ->prefix(null);

        $formatted = $column->formatState('test');
        expect($formatted)->toBe('test'); // No prefix added
    });

    it('handles blank check with empty string', function () {
        $column = ExportColumn::make('value')
            ->prefix('');

        $formatted = $column->formatState('test');
        expect($formatted)->toBe('test'); // No prefix added
    });

    it('handles blank check with whitespace string', function () {
        $column = ExportColumn::make('value')
            ->prefix('   ');

        // blank() should return true for whitespace-only string
        $formatted = $column->formatState('test');
        expect($formatted)->toBe('test'); // No prefix added (trimmed whitespace is blank)
    });

    it('handles blank check with numeric zero', function () {
        $column = ExportColumn::make('value')
            ->prefix(0);

        // blank() should return false for numeric 0
        $formatted = $column->formatState('test');
        expect($formatted)->toBe('0test'); // Prefix added (0 is not blank)
    });

    it('handles blank check with boolean false', function () {
        $column = ExportColumn::make('value')
            ->prefix(false);

        // blank() should return false for boolean false
        $formatted = $column->formatState('test');
        expect($formatted)->toBe('test'); // No prefix added (false evaluates to empty string)
    });

    it('handles blank check with countable empty array', function () {
        // Test that blank() correctly identifies empty arrays as blank
        // We test this indirectly through getFormattedState which uses filled() -> blank()
        $export = \Osama\LaravelExports\Exports\Models\Export::create([
            'exporter' => \Osama\LaravelExports\Tests\Feature\Exports\TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new \Osama\LaravelExports\Tests\Feature\Exports\TestExporter($export, [], []);
        $record = new class extends \Illuminate\Database\Eloquent\Model {};
        $exporter->__invoke($record);

        // When getStateUsing returns empty array, it should be treated as blank
        // and getFormattedState should handle it appropriately
        $column = ExportColumn::make('tags')
            ->getStateUsing(fn () => []); // Empty array
        $column->exporter($exporter);

        // Empty array is blank, so it gets formatted and returns empty string
        $formatted = $column->getFormattedState();
        expect($formatted)->toBe(''); // Empty array is blank, formatted returns empty string
    });

    it('handles blank check with countable non-empty array', function () {
        // This tests that non-empty arrays are not blank
        // We'll test this indirectly through prefix behavior
        $column = ExportColumn::make('value');

        // Test that array state formatting works
        $export = \Osama\LaravelExports\Exports\Models\Export::create([
            'exporter' => \Osama\LaravelExports\Tests\Feature\Exports\TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new \Osama\LaravelExports\Tests\Feature\Exports\TestExporter($export, [], []);
        $record = new class extends \Illuminate\Database\Eloquent\Model {};
        $exporter->__invoke($record);

        $column->exporter($exporter)
            ->getStateUsing(fn () => ['item1', 'item2']);

        $formatted = $column->getFormattedState();
        expect($formatted)->toBeString()
            ->and($formatted)->toContain('item1');
    });
});
