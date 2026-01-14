<?php

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Jobs\ExportCsv;
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();

    // Create a test table
    DB::statement('CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT)');
    DB::table('test_table')->insert([
        ['id' => 1, 'name' => 'Test 1'],
        ['id' => 2, 'name' => 'Test 2'],
        ['id' => 3, 'name' => 'Test 3'],
    ]);
});

describe('ExportCsv Job', function () {
    it('can process records and create csv file', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 3,
            'status' => ExportStatus::Processing,
        ]);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [1, 2, 3];

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            1,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        $export->refresh();

        expect($export->processed_rows)->toBe(3)
            ->and($export->successful_rows)->toBe(3);

        $filePath = $export->getFileDirectory().DIRECTORY_SEPARATOR.'0000000000000001.csv';
        expect(Storage::disk('local')->exists($filePath))->toBeTrue();
    });

    it('handles exceptions when processing records', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 3,
            'status' => ExportStatus::Processing,
        ]);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [999]; // Non-existent ID - find() returns empty collection

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            1,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        $export->refresh();

        // When find() returns empty collection, foreach doesn't execute
        // So processed_rows stays at 0
        expect($export->processed_rows)->toBe(0)
            ->and($export->successful_rows)->toBe(0);
    });

    it('sets authenticated user when creator exists', function () {
        $user = \Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
        $user->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);

        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
            'creator_type' => 'App\Models\User',
            'creator_id' => 1,
        ]);

        // Mock the creator relationship
        $export->setRelation('creator', $user);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [1];

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            1,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        expect(Auth::check())->toBeTrue();
    });

    afterEach(function () {
        \Mockery::close();
    });

    it('returns correct middleware', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        $job = new ExportCsv(
            $export,
            'serialized',
            [1],
            1,
            ['id' => 'ID'],
            []
        );

        $middleware = $job->middleware();
        expect($middleware)->toBeArray()
            ->and(count($middleware))->toBeGreaterThan(0);
    });

    it('returns correct retry until time', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        $job = new ExportCsv(
            $export,
            'serialized',
            [1],
            1,
            ['id' => 'ID'],
            []
        );

        $retryUntil = $job->retryUntil();
        expect($retryUntil)->not->toBeNull();
    });

    it('returns correct backoff', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        $job = new ExportCsv(
            $export,
            'serialized',
            [1],
            1,
            ['id' => 'ID'],
            []
        );

        $backoff = $job->backoff();
        expect($backoff)->toBeArray();
    });

    it('returns correct tags', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        $job = new ExportCsv(
            $export,
            'serialized',
            [1],
            1,
            ['id' => 'ID'],
            []
        );

        $tags = $job->tags();
        expect($tags)->toBeArray()
            ->and($tags[0])->toContain('export');
    });

    it('creates zero-padded file names', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [1];

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            42, // Page 42
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        $filePath = $export->getFileDirectory().DIRECTORY_SEPARATOR.'0000000000000042.csv';
        expect(Storage::disk('local')->exists($filePath))->toBeTrue();
    });

    it('prevents processed_rows from exceeding total_rows', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 2,
            'processed_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [1, 2, 3]; // More than total_rows

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            1,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        $export->refresh();

        // Should cap at total_rows
        expect($export->processed_rows)->toBe(2);
    });

    it('prevents successful_rows from exceeding total_rows', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 2,
            'successful_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [1, 2, 3]; // More than total_rows

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            1,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        $export->refresh();

        // Should cap at total_rows
        expect($export->successful_rows)->toBe(2);
    });

    it('handles exceptions during record processing', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        // Create a model instance for the query
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };
        $query = $model->newQuery();
        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $records = [1];

        $job = new ExportCsv(
            $export,
            $serializedQuery,
            $records,
            1,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        // Create a failing exporter that throws exceptions
        $failingExporter = new class($export, ['id' => 'ID', 'name' => 'Name'], []) extends TestExporter
        {
            public function __invoke($record): array
            {
                throw new \Exception('Processing failed');
            }
        };

        // Use reflection to replace the exporter
        $exporterReflection = new \ReflectionClass($job);
        $exporterProperty = $exporterReflection->getProperty('exporter');
        $exporterProperty->setAccessible(true);
        $exporterProperty->setValue($job, $failingExporter);

        // Should not throw, but report the exception
        $job->handle();

        $export->refresh();

        // Processed rows should increment, but successful rows should not
        expect($export->processed_rows)->toBe(1)
            ->and($export->successful_rows)->toBe(0);
    });
});
