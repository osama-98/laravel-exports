<?php

namespace Osama\LaravelExports\Exports\Enums\Contracts;

use Osama\LaravelExports\Exports\Downloader\Contracts\Downloader;

interface ExportFormat
{
    public function getDownloader(): Downloader;

    public function getExtension(): string;

    public function getMimeType(): string;
}
