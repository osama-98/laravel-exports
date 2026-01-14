<?php

namespace Osama\LaravelExports\Exports\Events;

use Osama\LaravelExports\Exports\Models\Export;

class ExportCompleted
{
    public function __construct(
        public Export $export,
    ) {}
}
