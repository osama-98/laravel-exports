<?php

namespace Osama\LaravelExports\Exports;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Closure;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;
use Osama\LaravelExports\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;
use Osama\LaravelExports\Exports\Enums\ExportFormat;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Jobs\CreateXlsxFile;
use Osama\LaravelExports\Exports\Jobs\ExportCompletion;
use Osama\LaravelExports\Exports\Jobs\PrepareCsvExport;
use Osama\LaravelExports\Exports\Models\Export;
use Throwable;

class ExportManager
{
    /**
     * @var class-string<Exporter>
     */
    protected string $exporter;

    protected ?string $job = null;

    protected int|Closure $chunkSize = 100;

    protected int|Closure|null $maxRows = null;

    protected string|Closure|null $fileDisk = null;

    protected string|Closure|null $fileName = null;

    /**
     * @var array<ExportFormatInterface> | Closure | null
     */
    protected array|Closure|null $formats = null;

    protected ?Closure $modifyQueryUsing = null;

    /**
     * @var array<string, mixed> | Closure
     */
    protected array|Closure $options = [];

    /**
     * @param  class-string<Exporter>  $exporter
     */
    public function exporter(string $exporter): static
    {
        $this->exporter = $exporter;

        return $this;
    }

    /**
     * @param  class-string | null  $job
     */
    public function job(?string $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function chunkSize(int|Closure $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function maxRows(int|Closure|null $rows): static
    {
        $this->maxRows = $rows;

        return $this;
    }

    public function fileDisk(string|Closure|null $disk): static
    {
        $this->fileDisk = $disk;

        return $this;
    }

    public function fileName(string|Closure|null $name): static
    {
        $this->fileName = $name;

        return $this;
    }

    /**
     * @param  array<ExportFormatInterface> | Closure | null  $formats
     */
    public function formats(array|Closure|null $formats): static
    {
        $this->formats = $formats;

        return $this;
    }

    public function modifyQueryUsing(?Closure $callback): static
    {
        $this->modifyQueryUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, mixed> | Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return class-string<Exporter>
     */
    public function getExporter(): string
    {
        return $this->exporter;
    }

    /**
     * @return class-string
     */
    public function getJob(): string
    {
        return $this->job ?? PrepareCsvExport::class;
    }

    public function getChunkSize(): int
    {
        return $this->evaluate($this->chunkSize);
    }

    public function getMaxRows(): ?int
    {
        return $this->evaluate($this->maxRows);
    }

    public function getFileDisk(): ?string
    {
        return $this->evaluate($this->fileDisk);
    }

    public function getFileName(Export $export): ?string
    {
        return $this->evaluate($this->fileName, [
            'export' => $export,
        ]);
    }

    /**
     * @return array<ExportFormatInterface> | null
     */
    public function getFormats(): ?array
    {
        return $this->evaluate($this->formats);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    /**
     * Start the export process.
     */
    public function start(Builder|QueryBuilder|null $query = null, ?array $records = null, ?array $columnMap = null, $creator = null): Export
    {
        $exporter = $this->getExporter();

        if (! $query) {
            $query = $exporter::getModel()::query();
        }

        // Convert QueryBuilder to Builder if needed, before modifyQuery
        if ($query instanceof QueryBuilder) {
            $query = $this->convertQueryBuilderToBuilder($query, $exporter);
        }

        $query = $exporter::modifyQuery($query);

        $options = $this->getOptions();

        if ($this->modifyQueryUsing) {
            $query = $this->evaluate($this->modifyQueryUsing, [
                'query' => $query,
                'options' => $options,
            ]) ?? $query;
        }

        // At this point, $query is always a Builder (converted if needed)
        $totalRows = $records ? count($records) : $query->toBase()->getCountForPagination();

        if ((! $records) && $query->getQuery()->limit) {
            $totalRows = min($totalRows, $query->getQuery()->limit);
        }

        $maxRows = $this->getMaxRows() ?? $totalRows;

        if ($maxRows < $totalRows) {
            throw new \Exception("Export exceeds maximum rows limit of {$maxRows}.");
        }

        // Build column map if not provided
        if ($columnMap === null) {
            $columnMap = collect($exporter::getColumns())
                ->filter(fn (ExportColumn $column): bool => $column->isEnabledByDefault())
                ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
                ->all();
        }

        if (empty($columnMap)) {
            throw new \Exception('No columns selected for export.');
        }

        $export = app(Export::class);
        if ($creator) {
            if (is_object($creator) && isset($creator->id) && isset($creator->type)) {
                // Handle plain object with id and type properties
                $export->creator_id = $creator->id;
                $export->creator_type = $creator->type;
            } else {
                // Handle Eloquent model
                $export->creator()->associate($creator);
            }
        }
        $export->exporter = $exporter;
        $export->total_rows = $totalRows;
        $export->status = ExportStatus::Processing;

        $exporterInstance = $export->getExporter(
            columnMap: $columnMap,
            options: $options,
        );

        $export->file_disk = $this->getFileDisk() ?? $exporterInstance->getFileDisk();

        // Temporary save to obtain the sequence number of the export file.
        $export->file_name = 'temp-'.time();
        $export->save();

        // Delete the export directory to prevent data contamination from previous exports with the same ID.
        $export->deleteFileDirectory();

        $export->file_name = $this->getFileName($export) ?? $exporterInstance->getFileName($export);
        $export->save();

        $formats = $this->getFormats() ?? $exporterInstance->getFormats();
        $hasCsv = in_array(ExportFormat::Csv, $formats);
        $hasXlsx = in_array(ExportFormat::Xlsx, $formats);

        $serializedQuery = EloquentSerializeFacade::serialize($query);

        $job = $this->getJob();
        $jobQueue = $exporterInstance->getJobQueue();
        $jobConnection = $exporterInstance->getJobConnection();
        $jobBatchName = $exporterInstance->getJobBatchName();

        // We do not want to send the loaded creator relationship to the queue in job payloads,
        // in case it contains attributes that are not serializable, such as binary columns.
        $export->unsetRelation('creator');

        $makeCreateXlsxFileJob = fn (): CreateXlsxFile => app(CreateXlsxFile::class, [
            'export' => $export,
            'columnMap' => $columnMap,
            'options' => $options,
        ]);

        $bus = Bus::batch([app($job, [
            'export' => $export,
            'query' => $serializedQuery,
            'columnMap' => $columnMap,
            'options' => $options,
            'chunkSize' => $this->getChunkSize(),
            'records' => $records,
        ])])
            ->allowFailures()
            ->catch(function (Throwable $e) use ($export) {
                $export->update(['status' => ExportStatus::Failed]);
            })
            ->when(
                filled($jobQueue),
                fn (PendingBatch $batch) => $batch->onQueue($jobQueue),
            )
            ->when(
                filled($jobConnection),
                fn (PendingBatch $batch) => $batch->onConnection($jobConnection),
            )
            ->when(
                filled($jobBatchName),
                fn (PendingBatch $batch) => $batch->name($jobBatchName),
            );

        Bus::chain([
            $bus,
            ...(($hasXlsx && ! $hasCsv) ? [$makeCreateXlsxFileJob()] : []),
            app(ExportCompletion::class, [
                'export' => $export,
                'columnMap' => $columnMap,
                'formats' => $formats,
                'options' => $options,
            ]),
            ...(($hasXlsx && $hasCsv) ? [$makeCreateXlsxFileJob()] : []),
        ])
            ->catch(function (Throwable $e) use ($export) {
                $export->update(['status' => ExportStatus::Failed]);
            })
            ->when(
                filled($jobQueue),
                fn (PendingChain $chain) => $chain->onQueue($jobQueue),
            )
            ->when(
                filled($jobConnection),
                fn (PendingChain $chain) => $chain->onConnection($jobConnection),
            )
            ->dispatch();

        return $export;
    }

    protected function evaluate($value, array $parameters = [])
    {
        if ($value instanceof Closure) {
            return app()->call($value, $parameters);
        }

        return $value;
    }

    protected function filled($value): bool
    {
        return ! blank($value);
    }

    protected function blank($value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof \Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }

    /**
     * Convert QueryBuilder to Eloquent Builder for serialization.
     * This is necessary because EloquentSerializeFacade only works with Eloquent Builder.
     */
    protected function convertQueryBuilderToBuilder(QueryBuilder $queryBuilder, string $exporter): Builder
    {
        $modelClass = $exporter::getModel();
        $reflection = new \ReflectionClass($modelClass);

        // If model is abstract or Model::class, create a concrete model instance
        if ($reflection->isAbstract() || $modelClass === Model::class) {
            $tableName = $queryBuilder->from;
            // Create a simple concrete model class dynamically
            $modelClass = get_class(new class extends Model {});
            $model = new $modelClass;
            $model->setTable($tableName);
            $eloquentQuery = $model->newQuery();
        } else {
            $eloquentQuery = $modelClass::query();
        }

        // Copy all query properties from QueryBuilder to Eloquent Builder's base query
        $baseQuery = $eloquentQuery->getQuery();
        $baseQuery->from = $queryBuilder->from;
        $baseQuery->mergeBindings($queryBuilder);

        // Copy where clauses
        if (! empty($queryBuilder->wheres)) {
            $baseQuery->wheres = $queryBuilder->wheres;
        }

        // Copy orders
        if (! empty($queryBuilder->orders)) {
            $baseQuery->orders = $queryBuilder->orders;
        }

        // Copy aggregates, groups, havings
        if (! empty($queryBuilder->aggregate)) {
            $baseQuery->aggregate = $queryBuilder->aggregate;
        }

        if (! empty($queryBuilder->groups)) {
            $baseQuery->groups = $queryBuilder->groups;
        }

        if (! empty($queryBuilder->havings)) {
            $baseQuery->havings = $queryBuilder->havings;
        }

        // Copy distinct
        if ($queryBuilder->distinct) {
            $baseQuery->distinct = $queryBuilder->distinct;
        }

        // Copy limit and offset
        if ($queryBuilder->limit !== null) {
            $eloquentQuery->limit($queryBuilder->limit);
        }

        if ($queryBuilder->offset !== null) {
            $eloquentQuery->offset($queryBuilder->offset);
        }

        return $eloquentQuery;
    }
}
