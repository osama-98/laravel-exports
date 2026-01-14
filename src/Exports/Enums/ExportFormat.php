<?php

namespace Osama\LaravelExports\Exports\Enums;

use Osama\LaravelExports\Exports\Downloader\Contracts\Downloader;
use Osama\LaravelExports\Exports\Downloader\CsvDownloader;
use Osama\LaravelExports\Exports\Downloader\XlsxDownloader;
use Osama\LaravelExports\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;

enum ExportFormat: string implements ExportFormatInterface
{
    case Csv = 'csv';

    case Xlsx = 'xlsx';

    public function getDownloader(): Downloader
    {
        return match ($this) {
            self::Csv => app(CsvDownloader::class),
            self::Xlsx => app(XlsxDownloader::class),
        };
    }

    public function getExtension(): string
    {
        return $this->value;
    }

    public function getMimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
