<?php

use Illuminate\Support\Facades\DB;
use Osama\LaravelExports\Facades\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;

describe('Export Facade', function () {
    beforeEach(function () {
        DB::statement('CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT)');
    });

    it('can access exporter method', function () {
        $manager = Export::exporter(TestExporter::class);

        expect($manager)->toBeInstanceOf(\Osama\LaravelExports\Exports\ExportManager::class);
    });

    it('can chain methods', function () {
        $query = DB::table('test_table');

        $manager = Export::exporter(TestExporter::class)
            ->chunkSize(100)
            ->maxRows(1000);

        expect($manager)->toBeInstanceOf(\Osama\LaravelExports\Exports\ExportManager::class);
    });
});
