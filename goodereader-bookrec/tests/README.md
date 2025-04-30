# Testing Good E-Reader Book Recommender

This directory contains test files for the Good E-Reader Book Recommender WordPress plugin.

## Setup

The test suite uses PHPUnit and the WordPress test framework. There are two approaches to testing:

1. **WordPress Test Framework** - For integration tests that require the WordPress environment
2. **Brain Monkey** - For unit tests that can run without WordPress

## Installation

### Requirements

- PHP 8.0+
- MySQL/MariaDB database (for WordPress tests)
- Composer

### Install Dependencies

```bash
composer install
```

### Set up WordPress Test Environment

You can set up the WordPress test environment using the included script:

```bash
# ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
./bin/install-wp-tests.sh wordpress_test root root localhost latest
```

## Running Tests

Once everything is set up, you can run the PHPUnit tests:

```bash
# Run all tests
vendor/bin/phpunit

# Run a specific test file
vendor/bin/phpunit tests/php/test-sample.php

# Run a specific test case
vendor/bin/phpunit --filter=Test_Sample
```

## Testing Strategy

Our tests are organized as follows:

- **Unit Tests**: Test individual components in isolation
- **Integration Tests**: Test components together with WordPress
- **WordPress-Independent Tests**: Use Brain Monkey to mock WordPress functions

## Adding New Tests

Create new test files in the `tests/php/` directory with the following naming convention:

- `test-*.php` for test files

When creating tests that need WordPress, extend `WP_UnitTestCase`:

```php
namespace GoodEReader\BookRec\Tests;

use WP_UnitTestCase;

class Test_Your_Feature extends WP_UnitTestCase {
    // Your tests here
}
```

For tests that don't need WordPress, use Brain Monkey:

```php
namespace GoodEReader\BookRec\Tests;

class Test_Your_Feature extends BrainMonkeySetup {
    // Your tests here
}
```

## Code Coverage

To generate a code coverage report:

```bash
vendor/bin/phpunit --coverage-html ./tests/coverage
```

Then open `./tests/coverage/index.html` in your browser.