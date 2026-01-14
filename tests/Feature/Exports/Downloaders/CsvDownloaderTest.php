<?php

use Illuminate\Support\Facades\Storage;
use Osama\LaravelExports\Exports\Downloader\CsvDownloader;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    Storage::fake('local');
});

describe('CsvDownloader', function () {
    it('can download csv file', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 10,
            'status' => ExportStatus::Completed,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);
        $disk->put($directory.'/headers.csv', 'id,name');
        $disk->put($directory.'/0000000000000001.csv', '1,John');

        $downloader = new CsvDownloader;
        $response = $downloader($export);

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and($response->headers->get('Content-Type'))->toBe('text/csv');
    });

    it('aborts with 404 when directory does not exist', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 10,
        ]);

        $downloader = new CsvDownloader;

        expect(fn () => $downloader($export))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('skips non-csv files when downloading', function () {
        /** @var Export $export */
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 10,
            'status' => ExportStatus::Completed,
        ]);

        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();
        $disk->makeDirectory($directory);
        $disk->put($directory.'/headers.csv', 'id,name');
        $disk->put($directory.'/0000000000000001.csv', '1,John');
        $disk->put($directory.'/some-file.txt', 'not a csv'); // Non-csv file

        $downloader = new CsvDownloader;
        $response = $downloader($export);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
    });
});
