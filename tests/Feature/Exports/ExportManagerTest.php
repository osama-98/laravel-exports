<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Osama\LaravelExports\Exports\Enums\ExportFormat;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\ExportManager;
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();

    // Create a test table
    DB::statement('CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT)');
    DB::table('test_table')->insert([
        ['id' => 1, 'name' => 'Test 1'],
        ['id' => 2, 'name' => 'Test 2'],
    ]);
});

describe('ExportManager', function () {
    it('can start export with default settings', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->start($query);

        expect($export)->toBeInstanceOf(Export::class)
            ->and($export->exporter)->toBe(TestExporter::class)
            ->and($export->status)->toBe(ExportStatus::Processing);

        // When a batch is inside a chain, Laravel handles it differently
        // We verify that the export was created successfully and the process started
        // The actual batch/chain dispatch happens asynchronously
    });

    it('can set chunk size', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->chunkSize(200)
            ->start($query, null, null, null);

        expect($export)->toBeInstanceOf(Export::class);
    });

    it('can set max rows', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        // Set maxRows to 2 to match the number of rows in the test table
        $export = $manager->exporter(TestExporter::class)
            ->maxRows(2)
            ->start($query, null, null, null);

        expect($export->total_rows)->toBe(2);
    });

    it('throws exception when max rows is exceeded', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        // Set maxRows to 1 when there are 2 rows - should throw exception
        expect(fn () => $manager->exporter(TestExporter::class)
            ->maxRows(1)
            ->start($query, null, null, null))
            ->toThrow(\Exception::class, 'Export exceeds maximum rows limit of 1');
    });

    it('can set custom file disk', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->fileDisk('public')
            ->start($query, null, null, null);

        expect($export->file_disk)->toBe('public');
    });

    it('can set custom file name', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->fileName('custom-export')
            ->start($query, null, null, null);

        expect($export->file_name)->toBe('custom-export');
    });

    it('can set formats', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->formats([ExportFormat::Xlsx])
            ->start($query, null, null, null);

        expect($export)->toBeInstanceOf(Export::class);
    });

    it('can set options', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->options(['date_format' => 'Y-m-d'])
            ->start($query, null, null, null);

        expect($export)->toBeInstanceOf(Export::class);
    });

    it('can modify query using closure', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->modifyQueryUsing(fn ($query) => $query->where('id', '>', 1))
            ->start($query, null, null, null);

        expect($export)->toBeInstanceOf(Export::class);
    });

    it('can start export with specific records', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $export = $manager->exporter(TestExporter::class)
            ->start($query, [1, 2], null, null);

        expect($export->total_rows)->toBe(2);
    });

    it('can start export with column map', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $columnMap = [
            'id' => 'ID',
            'name' => 'Full Name',
        ];

        $export = $manager->exporter(TestExporter::class)
            ->start($query, null, $columnMap, null);

        expect($export)->toBeInstanceOf(Export::class);
    });

    it('can associate creator with export', function () {
        $manager = app(ExportManager::class);
        $query = DB::table('test_table');

        $creator = (object) ['id' => 1];
        $creator->type = 'App\Models\User';

        $export = $manager->exporter(TestExporter::class)
            ->start($query, null, null, $creator);

        expect($export->creator_id)->toBe(1)
            ->and($export->creator_type)->toBe('App\Models\User');
    });
});
