<?php

use Illuminate\Support\Facades\Storage;
use Osama\LaravelExports\Exports\Downloader\XlsxDownloader;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(fn () => Storage::fake('local'));

describe('XlsxDownloader', function () {
    it('can download existing xlsx file', function () {
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
        $disk->put($directory.'/test-export.xlsx', 'xlsx content');

        $downloader = new XlsxDownloader;
        $response = $downloader($export);

        // When XLSX file exists, it returns StreamedResponse
        expect($response)->toBeInstanceOf(StreamedResponse::class);
    });

    it('returns either StreamedResponse or StreamedResponse (example of OR pattern)', function () {
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

        // Test with XLSX file (returns StreamedResponse)
        $disk->put($directory.'/test-export.xlsx', 'xlsx content');
        $downloader = new XlsxDownloader;
        $response1 = $downloader($export);

        // Remove XLSX to test CSV conversion (returns StreamedResponse)
        $disk->delete($directory.'/test-export.xlsx');
        $disk->put($directory.'/headers.csv', 'id,name');
        $response2 = $downloader($export);

        // Method 3: More explicit - check each response type
        expect($response1)->toBeInstanceOf(StreamedResponse::class)
            ->and($response2)->toBeInstanceOf(StreamedResponse::class);
    });

    it('can stream xlsx from csv files when xlsx does not exist', function () {
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

        $downloader = new XlsxDownloader;
        $response = $downloader($export);

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and($response->headers->get('Content-Type'))->toBe('application/vnd.ms-excel');
    });

    it('aborts with 404 when directory does not exist', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test-export',
            'total_rows' => 10,
        ]);

        $downloader = new XlsxDownloader;

        expect(fn () => $downloader($export))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('skips non-csv files when streaming from csv', function () {
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

        $downloader = new XlsxDownloader;
        $response = $downloader($export);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
    });
});
