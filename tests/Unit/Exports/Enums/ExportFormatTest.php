<?php

use Osama\LaravelExports\Exports\Downloader\Contracts\Downloader;
use Osama\LaravelExports\Exports\Enums\ExportFormat;

describe('ExportFormat', function () {
    it('has csv and xlsx cases', function () {
        expect(ExportFormat::cases())->toHaveCount(2)
            ->and(ExportFormat::Csv->value)->toBe('csv')
            ->and(ExportFormat::Xlsx->value)->toBe('xlsx');
    });

    it('can be created from string value', function () {
        expect(ExportFormat::from('csv'))->toBe(ExportFormat::Csv)
            ->and(ExportFormat::from('xlsx'))->toBe(ExportFormat::Xlsx);
    });

    it('returns correct extension', function () {
        expect(ExportFormat::Csv->getExtension())->toBe('csv')
            ->and(ExportFormat::Xlsx->getExtension())->toBe('xlsx');
    });

    it('returns correct mime type', function () {
        expect(ExportFormat::Csv->getMimeType())->toBe('text/csv')
            ->and(ExportFormat::Xlsx->getMimeType())->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('returns downloader instance', function () {
        expect(ExportFormat::Csv->getDownloader())->toBeInstanceOf(Downloader::class)
            ->and(ExportFormat::Xlsx->getDownloader())->toBeInstanceOf(Downloader::class);
    });
});
