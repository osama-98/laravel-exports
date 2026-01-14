<?php

namespace Osama\LaravelExports\Exports\Downloader\Contracts;

use Osama\LaravelExports\Exports\Models\Export;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface Downloader
{
    public function __invoke(Export $export): StreamedResponse;
}
