# Testing

This package uses [Pest PHP](https://pestphp.com) for testing.

## Running Tests

To run the tests, navigate to the package directory and run:

```bash
cd packages/osama-98/laravel-exports
./vendor/bin/pest
```

Or from the project root:

```bash
./vendor/bin/pest packages/osama-98/laravel-exports/tests
```

## Test Structure

- **Unit Tests** (`tests/Unit/`): Test individual components in isolation
  - Enums (ExportFormat, ExportStatus)
  - ExportColumn
  - Export Model methods

- **Feature Tests** (`tests/Feature/`): Test integration between components
  - ExportManager
  - Jobs (ExportCompletion)
  - Downloaders (CsvDownloader, XlsxDownloader)

## Writing Tests

Follow Pest PHP best practices:
- Use descriptive test names
- Group related tests with `describe()`
- Use `beforeEach()` and `afterEach()` for setup/teardown
- Use expectations API for assertions
- Mock external dependencies when needed

