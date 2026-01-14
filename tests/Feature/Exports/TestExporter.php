<?php

namespace Osama\LaravelExports\Tests\Feature\Exports;

use Illuminate\Database\Eloquent\Model;
use Osama\LaravelExports\Exports\ExportColumn;
use Osama\LaravelExports\Exports\Exporter;
use Osama\LaravelExports\Exports\Models\Export;

class TestExporter extends Exporter
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        // Return a generic model class for testing
        return Model::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Name'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return "Export completed with {$export->successful_rows} rows.";
    }
}
