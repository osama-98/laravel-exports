<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Osama\LaravelExports\Exports\Enums\ExportFormat;
use Osama\LaravelExports\Exports\Enums\ExportStatus;
use Osama\LaravelExports\Exports\Events\ExportCompleted;
use Osama\LaravelExports\Exports\Jobs\ExportCompletion;
use Osama\LaravelExports\Exports\Models\Export;

beforeEach(function () {
    Event::fake();
});

afterEach(function () {
    Mockery::close();
});

describe('ExportCompletion Job', function () {
    it('updates export status to completed', function () {
        $export = Export::create([
            'exporter' => 'Osama\LaravelExports\Tests\Feature\Exports\TestExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 10,
            'status' => ExportStatus::Processing,
        ]);

        $job = new ExportCompletion(
            $export,
            ['id' => 'ID', 'name' => 'Name'],
            [ExportFormat::Csv],
            []
        );

        $job->handle();

        $export->refresh();

        expect($export->status)->toBe(ExportStatus::Completed)
            ->and($export->completed_at)->not->toBeNull();
    });

    it('dispatches export completed event', function () {
        // Create a mock Authenticatable user
        $user = Mockery::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);

        $export = Export::create([
            'exporter' => 'Osama\LaravelExports\Tests\Feature\Exports\TestExporter',
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 10,
            'status' => ExportStatus::Processing,
        ]);

        // Associate the creator with the export
        $export->creator()->associate($user);
        $export->save();

        $job = new ExportCompletion(
            $export,
            ['id' => 'ID'],
            [ExportFormat::Csv],
            []
        );

        $job->handle();

        Event::assertDispatched(ExportCompleted::class, function ($event) use ($export) {
            return $event->export->id === $export->id;
        });
    });
});
