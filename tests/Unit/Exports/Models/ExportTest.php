<?php

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Models\Export;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    Storage::fake('local');
});

describe('Export Model', function () {
    it('can be created', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 100,
            'status' => ExportStatus::Pending,
        ]);

        expect($export)->toBeInstanceOf(Export::class)
            ->and($export->exporter)->toBe('App\Exports\UserExporter')
            ->and($export->file_disk)->toBe('local')
            ->and($export->file_name)->toBe('test-export')
            ->and($export->total_rows)->toBe(100)
            ->and($export->status)->toBe(ExportStatus::Pending);
    });

    it('casts status to enum', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 10,
            'status' => 'completed',
        ]);

        expect($export->status)->toBeInstanceOf(ExportStatus::class)
            ->and($export->status)->toBe(ExportStatus::Completed);
    });

    it('calculates failed rows count correctly', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 100,
            'successful_rows' => 95,
        ]);

        expect($export->getFailedRowsCount())->toBe(5);
    });

    it('returns file disk instance', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 10,
        ]);

        expect($export->getFileDisk())->toBeInstanceOf(Filesystem::class);
    });

    it('generates correct file directory path', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 10,
        ]);

        expect($export->getFileDirectory())->toBe('exports'.DIRECTORY_SEPARATOR.$export->id);
    });

    it('can delete file directory', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 10,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);
        $disk->put($directory.'/test.txt', 'content');

        expect($disk->exists($directory))->toBeTrue();

        $export->deleteFileDirectory();

        expect($disk->exists($directory))->toBeFalse();
    });

    it('can download export file', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 10,
            'status' => ExportStatus::Completed,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);
        $disk->put($directory.'/headers.csv', 'header1,header2');

        $response = $export->download();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
    });

    it('returns 0 when total rows is 0', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
            'processed_rows' => 0,
        ]);

        expect($export->progressPercentage())->toBe(0.0);
    });

    it('returns 0 when processed rows is 0', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 100,
            'processed_rows' => 0,
        ]);

        expect($export->progressPercentage())->toBe(0.0);
    });

    it('returns 100 when export is completed', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 100,
            'processed_rows' => 100,
        ]);

        expect($export->progressPercentage())->toBe(100.0);
    });

    it('returns 50 when half is processed', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 100,
            'processed_rows' => 50,
        ]);

        expect($export->progressPercentage())->toBe(50.0);
    });

    it('returns correct percentage for partial progress', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 1000,
            'processed_rows' => 333,
        ]);

        expect($export->progressPercentage())->toBe(33.3);
    });

    it('rounds to 2 decimal places', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 3,
            'processed_rows' => 1,
        ]);

        $percentage = $export->progressPercentage();
        expect($percentage)->toBe(33.33)
            ->and($percentage)->toBeFloat();
    });

    it('handles when processed rows exceed total rows', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => 'App\Exports\UserExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 100,
            'processed_rows' => 150,
        ]);

        // Should still calculate based on processed_rows / total_rows
        expect($export->progressPercentage())->toBe(150.0);
    });
});
