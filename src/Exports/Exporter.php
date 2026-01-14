<?php

namespace Osama\LaravelExports\Exports;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Osama\LaravelExports\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;
use Osama\LaravelExports\Exports\Enums\ExportFormat;
use Osama\LaravelExports\Exports\Models\Export;

abstract class Exporter
{
    /** @var array<ExportColumn> */
    protected array $cachedColumns;

    protected ?Model $record;

    /**
     * @var class-string<Model>|null
     */
    protected static ?string $model = null;

    /**
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected Export $export,
        protected array $columnMap,
        protected array $options,
    ) {}

    /**
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        $this->record = $record;

        $columns = $this->getCachedColumns();

        $data = [];

        foreach (array_keys($this->columnMap) as $column) {
            $data[] = $columns[$column]->getFormattedState();
        }

        return $data;
    }

    /**
     * @return array<ExportColumn>
     */
    abstract public static function getColumns(): array;

    /**
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        return static::$model ?? (string) str(class_basename(static::class))
            ->beforeLast('Exporter')
            ->prepend(app()->getNamespace().'Models\\');
    }

    abstract public static function getCompletedNotificationBody(Export $export): string;

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Export completed';
    }

    /**
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        return [
            (new WithoutOverlapping("export{$this->export->getKey()}"))->expireAfter(600),
        ];
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addDay();
    }

    /**
     * @return int | array<int> | null
     */
    public function getJobBackoff(): int|array|null
    {
        return [60, 120, 300, 600];
    }

    /**
     * @return array<int, string>
     */
    public function getJobTags(): array
    {
        return ["export{$this->export->getKey()}"];
    }

    public function getJobQueue(): ?string
    {
        return null;
    }

    public function getJobConnection(): ?string
    {
        return null;
    }

    public function getJobBatchName(): ?string
    {
        return null;
    }

    /**
     * @return array<ExportColumn>
     */
    public function getCachedColumns(): array
    {
        return $this->cachedColumns ??= array_reduce(static::getColumns(), function (array $carry, ExportColumn $column): array {
            $carry[$column->getName()] = $column->exporter($this);

            return $carry;
        }, []);
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getFileDisk(): string
    {
        return config('exports.file_disk', 'local');
    }

    public function getFileName(Export $export): string
    {
        $model = static::getModel();
        $modelLabel = $model ? class_basename($model) : 'export';

        return str($modelLabel)
            ->plural()
            ->kebab()
            ->append('-')
            ->append($export->id)
            ->toString();
    }

    public static function getCsvDelimiter(): string
    {
        return ',';
    }

    /**
     * @return array<ExportFormatInterface>
     */
    public function getFormats(): array
    {
        return [ExportFormat::Csv, ExportFormat::Xlsx];
    }

    public function getXlsxCellStyle(): ?Style
    {
        return null;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return null;
    }

    public function getXlsxWriterOptions(): ?Options
    {
        return null;
    }

    /**
     * @param  array<mixed>  $values
     */
    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        return $this->makeXlsxRow($values, $style);
    }

    /**
     * @param  array<mixed>  $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        return Row::fromValues($values, $style);
    }

    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        return $writer;
    }

    /**
     * @template TModel of Model
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query;
    }
}
