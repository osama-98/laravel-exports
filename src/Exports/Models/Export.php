<?php

namespace Osama\LaravelExports\Exports\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Osama\LaravelExports\Exports\Enums\ExportFormat;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Exporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property int $id
 * @property CarbonInterface|null $completed_at
 * @property string $file_disk
 * @property string $file_name
 * @property class-string<Exporter> $exporter
 * @property int $processed_rows
 * @property int $total_rows
 * @property int $successful_rows
 * @property ExportStatus $status
 * @property string $creator_type
 * @property int $creator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Authenticatable $creator
 *
 * @mixin Builder
 */
class Export extends Model
{
    use Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'timestamp',
            'processed_rows' => 'integer',
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'status' => ExportStatus::class,
        ];
    }

    protected $fillable = [
        'exporter',
        'file_disk',
        'file_name',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'status',
        'completed_at',
        'creator_type',
        'creator_id',
    ];

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function getExporter(
        array $columnMap,
        array $options,
    ): Exporter {
        return app($this->exporter, [
            'export' => $this,
            'columnMap' => $columnMap,
            'options' => $options,
        ]);
    }

    public function getFailedRowsCount(): int
    {
        return $this->total_rows - $this->successful_rows;
    }

    /**
     * Get the progress percentage of the export.
     *
     * @return float Returns a value between 0 and 100, rounded to 2 decimal places.
     */
    public function progressPercentage(): float
    {
        if ($this->total_rows <= 0) {
            return 0.0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function getFileDisk(): Filesystem
    {
        return Storage::disk($this->file_disk);
    }

    public function getFileDirectory(): string
    {
        return 'exports'.DIRECTORY_SEPARATOR.$this->getKey();
    }

    public function deleteFileDirectory(): void
    {
        $disk = $this->getFileDisk();
        $directory = $this->getFileDirectory();

        if ($disk->directoryExists($directory)) {
            $disk->deleteDirectory($directory);
        }
    }

    /**
     * Download the export file.
     */
    public function download(string $format = 'csv'): StreamedResponse
    {
        $formatEnum = ExportFormat::from($format);
        $downloader = $formatEnum->getDownloader();

        return $downloader($this);
    }
}
