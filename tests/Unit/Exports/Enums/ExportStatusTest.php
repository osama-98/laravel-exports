<?php

use Osama\LaravelExports\Exports\Enums\ExportStatus;

describe('ExportStatus', function () {
    it('has all required cases', function () {
        expect(ExportStatus::cases())->toHaveCount(4)
            ->and(ExportStatus::Pending->value)->toBe('pending')
            ->and(ExportStatus::Processing->value)->toBe('processing')
            ->and(ExportStatus::Completed->value)->toBe('completed')
            ->and(ExportStatus::Failed->value)->toBe('failed');
    });

    it('can be created from string value', function () {
        expect(ExportStatus::from('pending'))->toBe(ExportStatus::Pending)
            ->and(ExportStatus::from('processing'))->toBe(ExportStatus::Processing)
            ->and(ExportStatus::from('completed'))->toBe(ExportStatus::Completed)
            ->and(ExportStatus::from('failed'))->toBe(ExportStatus::Failed);
    });
});
