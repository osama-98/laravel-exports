<?php

namespace Osama\LaravelExports\Exports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Osama\LaravelExports\Exports\Enums\Contracts\ExportFormat;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Events\ExportCompleted;
use Osama\LaravelExports\Exports\Exporter;
use Osama\LaravelExports\Exports\Models\Export;

class ExportCompletion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    protected Exporter $exporter;

    /**
     * @param  array<string, string>  $columnMap
     * @param  array<ExportFormat>  $formats
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected Export $export,
        protected array $columnMap,
        protected array $formats,
        protected array $options,
    ) {
        $this->exporter = $this->export->getExporter(
            $this->columnMap,
            $this->options,
        );
    }

    public function handle(): void
    {
        $this->export->update([
            'completed_at' => now(),
            'status' => ExportStatus::Completed,
        ]);

        if (! $this->export->creator instanceof Authenticatable) {
            return;
        }

        event(new ExportCompleted($this->export));
    }
}
