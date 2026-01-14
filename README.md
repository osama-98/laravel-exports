# Laravel Exports

[![Latest Version on Packagist](https://img.shields.io/packagist/v/osama-98/laravel-exports.svg?style=flat-square)](https://packagist.org/packages/osama-98/laravel-exports)
[![Total Downloads](https://img.shields.io/packagist/dt/osama-98/laravel-exports.svg)](https://packagist.org/packages/osama-98/laravel-exports)
[![License](https://img.shields.io/packagist/l/osama-98/laravel-exports.svg?style=flat-square)](https://packagist.org/packages/osama-98/laravel-exports)

A powerful Laravel package for exporting large datasets in batches and chunks with support for CSV and XLSX formats. This package provides a clean, fluent API for handling exports efficiently.

## Features

- ✅ **Batch & Chunk Processing** - Handle large datasets efficiently with configurable chunk sizes
- ✅ **Queue-Based Exports** - Process exports asynchronously using Laravel queues
- ✅ **Multiple Formats** - Export to both CSV and XLSX formats
- ✅ **Column Mapping** - Customize column names and labels
- ✅ **Progress Tracking** - Monitor export progress with detailed status information
- ✅ **Query Builder Support** - Works with both Eloquent Builder and Query Builder
- ✅ **Fluent API** - Laravel-style fluent interface for easy configuration
- ✅ **Relationship Support** - Export related model data with aggregations
- ✅ **Custom Formatting** - Format dates, numbers, and custom values
- ✅ **Storage Flexibility** - Support for local, S3, and custom storage disks
- ✅ **Event System** - Listen to export completion events

## Requirements

- PHP 8.1 or higher
- Laravel 10.0, 11.0, or 12.0
- Queue driver configured (database, redis, sqs, etc.)

## Installation

Install the package via Composer:

```bash
composer require osama-98/laravel-exports
```

Publish and run the migrations:

```bash
# Publish the package migrations
php artisan vendor:publish --tag=laravel-exports-migrations

# Publish Laravel's queue migrations (required for job batching)
php artisan queue:batches-table
php artisan migrate
```

**Note:** The `job_batches` table is required for queue batching functionality. If you're using the database queue driver, you'll also need the `jobs` and `failed_jobs` tables:

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

The package will automatically register its service provider.

## Quick Start

### 1. Create an Exporter

Create an exporter class that extends `Osama\LaravelExports\Exports\Exporter`:

```php
<?php

namespace App\Exports;

use App\Models\User;
use Carbon\Carbon;
use Osama\LaravelExports\Exports\ExportColumn;
use Osama\LaravelExports\Exports\Exporter;
use Osama\LaravelExports\Exports\Models\Export;

class UserExporter extends Exporter
{
    public static function getModel(): string
    {
        return User::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('name')
                ->label('Full Name'),

            ExportColumn::make('email')
                ->label('Email Address'),

            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('Y-m-d H:i:s') : null),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return "Your user export has completed with {$export->successful_rows} rows exported.";
    }
}
```

### 2. Start an Export

#### Using the Facade

```php
use App\Exports\UserExporter;
use App\Models\User;
use Osama\LaravelExports\Facades\Export as ExportFacade;
use Osama\LaravelExports\Exports\Enums\ExportFormat;

// Basic export to XLSX
$export = ExportFacade::exporter(UserExporter::class)
    ->formats([ExportFormat::Xlsx])
    ->start(User::query(), creator: auth()->user());

// Export to both CSV and XLSX
$export = ExportFacade::exporter(UserExporter::class)
    ->formats([ExportFormat::Csv, ExportFormat::Xlsx])
    ->chunkSize(500)
    ->start(User::query(), creator: auth()->user());
```

#### Using Dependency Injection

```php
use App\Exports\UserExporter;
use App\Models\User;
use Osama\LaravelExports\Exports\ExportManager;
use Osama\LaravelExports\Exports\Enums\ExportFormat;

public function exportUsers(ExportManager $exportManager)
{
    $export = $exportManager
        ->exporter(UserExporter::class)
        ->chunkSize(500)
        ->formats([ExportFormat::Csv, ExportFormat::Xlsx])
        ->start(User::query(), creator: auth()->user());

    return response()->json([
        'export_id' => $export->id,
        'status' => $export->status->value,
        'total_rows' => $export->total_rows,
    ]);
}
```

### 3. Check Export Status

```php
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Exports\Enums\ExportStatus;

$export = Export::find($exportId);

// Check status
if ($export->status === ExportStatus::Completed) {
    // Export is ready
}

// Get progress percentage
$progress = $export->progressPercentage();
```

### 4. Download Export File

```php
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Exports\Enums\ExportStatus;

public function downloadExport(Export $export, string $format = 'xlsx')
{
    if ($export->status !== ExportStatus::Completed) {
        abort(400, 'Export is not completed yet');
    }

    return $export->download($format);
}
```

## Usage Examples

### Export with Custom Column Mapping

```php
$export = ExportFacade::exporter(UserExporter::class)
    ->formats([ExportFormat::Xlsx])
    ->start(
        User::query(),
        columnMap: [
            'id' => 'User ID',
            'name' => 'Full Name',
            'email' => 'Email Address',
            'created_at' => 'Registration Date',
        ],
        creator: auth()->user()
    );
```

### Export with Query Modification

```php
$export = ExportFacade::exporter(UserExporter::class)
    ->modifyQueryUsing(function ($query, $options) {
        // Filter users created in the last 30 days
        return $query->where('created_at', '>=', now()->subDays(30));
    })
    ->formats([ExportFormat::Csv])
    ->start(User::query(), creator: auth()->user());
```

### Export Specific Records by IDs

```php
$userIds = User::take(100)->pluck('id')->toArray();

$export = ExportFacade::exporter(UserExporter::class)
    ->formats([ExportFormat::Csv])
    ->start(User::query(), records: $userIds, creator: auth()->user());
```

### Export with Max Rows Limit

```php
$export = ExportFacade::exporter(UserExporter::class)
    ->maxRows(1000)
    ->chunkSize(200)
    ->formats([ExportFormat::Csv])
    ->start(User::query(), creator: auth()->user());
```

### Export with Custom Options

```php
$export = ExportFacade::exporter(UserExporter::class)
    ->options([
        'date_format' => 'Y-m-d',
        'timezone' => 'UTC',
    ])
    ->fileDisk('local')
    ->fileName('custom-users-export')
    ->formats([ExportFormat::Xlsx])
    ->start(User::query(), creator: auth()->user());
```

### Export Using Query Builder

```php
use Illuminate\Support\Facades\DB;

$export = ExportFacade::exporter(UserExporter::class)
    ->formats([ExportFormat::Csv])
    ->start(DB::table('users')->where('active', true), creator: auth()->user());
```

### Export to S3

```php
$export = ExportFacade::exporter(UserExporter::class)
    ->fileDisk('s3') // Make sure 's3' disk is configured in config/filesystems.php
    ->formats([ExportFormat::Xlsx])
    ->start(User::query()->limit(1000), creator: auth()->user());
```

## Advanced Features

### Custom Column State

Customize how column values are extracted:

```php
ExportColumn::make('full_name')
    ->label('Full Name')
    ->getStateUsing(function ($user) {
        return "{$user->first_name} {$user->last_name}";
    });

// Use dot notation for relationships
ExportColumn::make('profile.bio')
    ->label('Biography');

// Use default value
ExportColumn::make('status')
    ->default('active');
```

### Column Formatting

Format dates, numbers, and add prefixes/suffixes:

```php
ExportColumn::make('price')
    ->label('Price')
    ->prefix('$')
    ->formatStateUsing(fn ($state) => number_format($state, 2));

ExportColumn::make('created_at')
    ->label('Created At')
    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('Y-m-d H:i:s') : null);

ExportColumn::make('description')
    ->label('Description')
    ->limit(100); // Limit text length
```

### Relationship Aggregations

Export aggregated data from relationships:

```php
ExportColumn::make('orders_count')
    ->label('Total Orders')
    ->counts('orders');

ExportColumn::make('orders_avg_amount')
    ->label('Average Order Amount')
    ->avg('orders', 'amount');

ExportColumn::make('orders_sum_amount')
    ->label('Total Order Amount')
    ->sum('orders', 'amount');

ExportColumn::make('has_orders')
    ->label('Has Orders')
    ->exists('orders');
```

### Query Modification in Exporter

Modify queries directly in your exporter class:

```php
class UserExporter extends Exporter
{
    public static function modifyQuery($query)
    {
        return $query->where('active', true)
            ->with('profile');
    }
}
```

### XLSX Styling

Customize XLSX cell styles:

```php
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\CellAlignment;

class UserExporter extends Exporter
{
    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontSize(12)
            ->setFontColor(Color::BLACK);
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontSize(14)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::BLUE)
            ->setCellAlignment(CellAlignment::CENTER);
    }
}
```

## Events

### ExportCompleted Event

The `ExportCompleted` event is dispatched when an export finishes processing. Listen to this event to send notifications or perform post-export actions.

**Example Listener:**

```php
// In your EventServiceProvider
use Osama\LaravelExports\Exports\Events\ExportCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(ExportCompleted::class, function (ExportCompleted $event) {
    $export = $event->export;
    
    // Send notification to user
    if ($export->creator) {
        $export->creator->notify(
            new ExportReadyNotification($export)
        );
    }
    
    // Or log the completion
    Log::info("Export {$export->id} completed with {$export->successful_rows} rows");
});
```

**Example Listener Class:**

```php
namespace App\Listeners;

use Osama\LaravelExports\Exports\Events\ExportCompleted;
use Illuminate\Support\Facades\Notification;

class SendExportCompletionNotification
{
    public function handle(ExportCompleted $event): void
    {
        $export = $event->export;
        
        if ($export->creator) {
            $export->creator->notify(
                new ExportReadyNotification($export)
            );
        }
    }
}
```

Register it in `EventServiceProvider`:

```php
protected $listen = [
    \Osama\LaravelExports\Exports\Events\ExportCompleted::class => [
        \App\Listeners\SendExportCompletionNotification::class,
    ],
];
```

## Queue Configuration

The package uses Laravel's queue batching system for processing exports. Make sure your queue is properly configured.

### Required Database Tables

If you're using the `database` queue driver, you need the following tables:

1. **`job_batches`** - Required for batch processing (used by this package)
2. **`jobs`** - Required for queue jobs
3. **`failed_jobs`** - Required for failed job tracking

Create these tables:

```bash
php artisan queue:batches-table
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

### Configure Queue Driver

**Configure queue in `.env`:**

```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
```

**Run queue worker:**

```bash
php artisan queue:work
```

For production, use a process manager like Supervisor to keep the queue worker running.

## API Reference

### ExportManager Methods

| Method | Description | Example |
|--------|-------------|---------|
| `exporter(string $exporter)` | Set the exporter class | `->exporter(UserExporter::class)` |
| `start(Builder\|QueryBuilder\|null $query, ?array $records, ?array $columnMap, $creator)` | Start the export | `->start(User::query(), creator: auth()->user())` |
| `chunkSize(int $size)` | Set chunk size (default: 100) | `->chunkSize(500)` |
| `maxRows(?int $rows)` | Set maximum rows limit | `->maxRows(10000)` |
| `formats(array $formats)` | Set export formats | `->formats([ExportFormat::Csv, ExportFormat::Xlsx])` |
| `fileDisk(?string $disk)` | Set storage disk | `->fileDisk('s3')` |
| `fileName(?string $name)` | Set custom file name | `->fileName('my-export')` |
| `modifyQueryUsing(?Closure $callback)` | Modify query before export | `->modifyQueryUsing(fn($q) => $q->where('active', true))` |
| `options(array $options)` | Set export options | `->options(['date_format' => 'Y-m-d'])` |

### ExportColumn Methods

| Method | Description | Example |
|--------|-------------|---------|
| `make(string $name)` | Create a new column | `ExportColumn::make('name')` |
| `label(string $label)` | Set column label | `->label('Full Name')` |
| `getStateUsing(Closure\|string $callback)` | Custom state extraction | `->getStateUsing(fn($r) => $r->name)` |
| `formatStateUsing(Closure $callback)` | Format the state value | `->formatStateUsing(fn($s) => ucfirst($s))` |
| `enabled(bool $enabled)` | Enable/disable by default | `->enabled(false)` |
| `default($value)` | Set default value | `->default('N/A')` |
| `prefix(string $prefix)` | Add prefix to value | `->prefix('$')` |
| `suffix(string $suffix)` | Add suffix to value | `->suffix(' USD')` |
| `limit(int $limit)` | Limit text length | `->limit(100)` |
| `counts(string $relationship)` | Count relationship records | `->counts('orders')` |
| `sum(string $relationship, string $column)` | Sum relationship column | `->sum('orders', 'amount')` |
| `avg(string $relationship, string $column)` | Average relationship column | `->avg('orders', 'amount')` |

### Export Model Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Export ID |
| `exporter` | string | Exporter class name |
| `status` | ExportStatus | Export status (Pending, Processing, Completed, Failed) |
| `total_rows` | int | Total number of rows to export |
| `processed_rows` | int | Number of rows processed |
| `successful_rows` | int | Number of rows successfully exported |
| `file_name` | string | Export file name |
| `file_disk` | string | Storage disk name |
| `completed_at` | Carbon\|null | Completion timestamp |
| `creator` | Model\|null | User who created the export (morphTo) |

### Export Model Methods

| Method | Description | Example |
|--------|-------------|---------|
| `download(string $format)` | Download export file | `$export->download('xlsx')` |
| `getFileDirectory()` | Get file directory path | `$export->getFileDirectory()` |
| `getFileDisk()` | Get Filesystem instance | `$export->getFileDisk()` |
| `getFailedRowsCount()` | Get count of failed rows | `$export->getFailedRowsCount()` |
| `progressPercentage()` | Get progress percentage (0-100) | `$export->progressPercentage()` |

## Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/osama-98/laravel-exports).

## Credits

Developed with ❤️ for the Laravel community.
