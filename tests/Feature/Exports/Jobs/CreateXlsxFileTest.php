<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Jobs\CreateXlsxFile;
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

describe('CreateXlsxFile Job', function () {
    it('creates xlsx file from csv chunks', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 3,
            'status' => ExportStatus::Processing,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);

        // Create headers.csv
        $disk->put($directory.'/headers.csv', "ID,Name\n");

        // Create chunk files
        $disk->put($directory.'/0000000000000001.csv', "1,Test 1\n");
        $disk->put($directory.'/0000000000000002.csv', "2,Test 2\n");
        $disk->put($directory.'/0000000000000003.csv', "3,Test 3\n");

        $job = new CreateXlsxFile(
            $export,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        $job->handle();

        // Check if XLSX file was created
        $xlsxPath = $directory.'/'.$export->file_name.'.xlsx';
        expect($disk->exists($xlsxPath))->toBeTrue();
    });

    it('skips non-csv files', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);

        // Create headers.csv
        $disk->put($directory.'/headers.csv', "ID,Name\n");

        // Create a non-csv file
        $disk->put($directory.'/some-file.txt', 'content');

        $job = new CreateXlsxFile(
            $export,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        // Should not throw an error
        expect(fn () => $job->handle())->not->toThrow(\Exception::class);
    });

    it('skips headers.csv when processing chunks', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 1,
            'status' => ExportStatus::Processing,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);

        // Create headers.csv
        $disk->put($directory.'/headers.csv', "ID,Name\n");

        // Create chunk file
        $disk->put($directory.'/0000000000000001.csv', "1,Test 1\n");

        $job = new CreateXlsxFile(
            $export,
            ['id' => 'ID', 'name' => 'Name'],
            []
        );

        // Should not throw an error
        expect(fn () => $job->handle())->not->toThrow(\Exception::class);

        $xlsxPath = $directory.'/'.$export->file_name.'.xlsx';
        expect($disk->exists($xlsxPath))->toBeTrue();
    });
});
