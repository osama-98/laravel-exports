<?php

namespace Osama\LaravelExports\Facades;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Osama\LaravelExports\Exports\ExportManager;

/**
 * @method static ExportManager exporter(string $exporter)
 * @method static ExportManager from(Builder|Collection|array $query)
 * @method static ExportManager columnMap(array $columnMap)
 * @method static ExportManager options(array $options)
 * @method static ExportManager chunkSize(int $size)
 * @method static ExportManager maxRows(?int $rows)
 * @method static ExportManager formats(array $formats)
 * @method static ExportManager fileDisk(?string $disk)
 * @method static ExportManager fileName(?string $name)
 * @method static ExportManager modifyQueryUsing(?Closure $callback)
 * @method static ExportManager columnMapping(bool $enabled = true)
 * @method static ExportManager authGuard(?string $guard)
 * @method static ExportManager userId(?int $userId)
 * @method static \Osama\LaravelExports\Exports\Models\Export dispatch()
 *
 * @see ExportManager
 */
class Export extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ExportManager::class;
    }
}
