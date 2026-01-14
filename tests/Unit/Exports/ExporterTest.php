<?php

use Illuminate\Support\Facades\Config;
use Osama\LaravelExports\Exports\Enums\ExportFormat;
use Osama\LaravelExports\Exports\Models\Export;
use Osama\LaravelExports\Tests\Feature\Exports\TestExporter;

describe('Exporter', function () {
    it('can get file disk from config', function () {
        Config::set('exports.file_disk', 's3');

        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getFileDisk())->toBe('s3');
    });

    it('can generate file name', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        $fileName = $exporter->getFileName($export);
        expect($fileName)->toContain($export->id);
    });

    it('can get csv delimiter', function () {
        expect(TestExporter::getCsvDelimiter())->toBe(',');
    });

    it('can get formats', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        $formats = $exporter->getFormats();
        expect($formats)->toContain(ExportFormat::Csv)
            ->and($formats)->toContain(ExportFormat::Xlsx);
    });

    it('can get xlsx cell style', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getXlsxCellStyle())->toBeNull();
    });

    it('can get xlsx header cell style', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getXlsxHeaderCellStyle())->toBeNull();
    });

    it('can get xlsx writer options', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getXlsxWriterOptions())->toBeNull();
    });

    it('can make xlsx header row', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        $row = $exporter->makeXlsxHeaderRow(['Header1', 'Header2']);
        expect($row)->toBeInstanceOf(\OpenSpout\Common\Entity\Row::class);
    });

    it('can make xlsx row', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        $row = $exporter->makeXlsxRow(['Value1', 'Value2']);
        expect($row)->toBeInstanceOf(\OpenSpout\Common\Entity\Row::class);
    });

    it('can configure xlsx writer before close', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        // Writer is final class, so we'll test that the method returns the writer
        // by creating a real instance (though it won't be fully functional without proper setup)
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        $writer = new \OpenSpout\Writer\XLSX\Writer;
        $writer->openToFile($tempFile);

        $result = $exporter->configureXlsxWriterBeforeClose($writer);
        expect($result)->toBe($writer);

        @unlink($tempFile);
    });

    it('can get completed notification title', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        expect(TestExporter::getCompletedNotificationTitle($export))->toBe('Export completed');
    });

    it('can get options', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $options = ['key' => 'value'];
        $exporter = new TestExporter($export, [], $options);

        expect($exporter->getOptions())->toBe($options);
    });

    it('can get record', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        // Record is null by default until __invoke is called
        // We'll test it returns the record when set
        $model = new class extends \Illuminate\Database\Eloquent\Model {};
        $exporter->__invoke($model);

        expect($exporter->getRecord())->toBe($model);
    });

    it('can get job queue', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getJobQueue())->toBeNull();
    });

    it('can get job connection', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getJobConnection())->toBeNull();
    });

    it('can get job batch name', function () {
        $export = Export::create([
            'exporter' => TestExporter::class,
            'file_disk' => 'local',
            'file_name' => 'test',
            'total_rows' => 0,
        ]);

        $exporter = new TestExporter($export, [], []);

        expect($exporter->getJobBatchName())->toBeNull();
    });

    it('can modify query', function () {
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_table';
        };

        $query = $model->newQuery();
        $modified = TestExporter::modifyQuery($query);

        expect($modified)->toBe($query);
    });

    afterEach(function () {
        \Mockery::close();
    });
});
