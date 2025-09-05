# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is `spojenet/abo-parser`, a PHP library for parsing Czechoslovak ABO format files. The library provides a single main class `AboParser` that can parse ABO files and convert them to structured data.

## Architecture

### Core Structure
- **Main Parser**: `src/AboParser/AboParser.php` - The primary parsing class
- **Namespace**: `SpojeNet\AboParser` (PSR-4 autoloading)
- **Example Usage**: `Example/abo2json.php` - Demonstrates library usage

### Code Standards
- **PHP Version**: PHP 8.4+ required
- **Coding Standard**: PSR-12
- **Static Analysis**: PHPStan level 6
- **Code Formatting**: PHP-CS-Fixer with Ergebnis configuration

## Development Commands

### Setup and Dependencies
```bash
# Install dependencies
composer install

# Or using Make
make vendor
```

### Code Quality
```bash
# Run static analysis
vendor/bin/phpstan analyse --configuration=phpstan-default.neon.dist --memory-limit=-1
# Or using Make
make static-code-analysis

# Generate static analysis baseline
make static-code-analysis-baseline

# Fix coding standards
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose --allow-risky=yes
# Or using Make
make cs

# Validate composer files
composer validate --strict
```

### Testing
```bash
# Run tests
vendor/bin/phpunit tests
# Or using Make
make tests
```

### All Available Make Commands
```bash
# Show all available Make targets
make help
```

## Development Guidelines

### Code Requirements
- All code comments and messages must be in English
- Include docblocks for all functions and classes with purpose, parameters, and return types
- Use meaningful variable names that describe their purpose
- Avoid magic numbers/strings - define constants instead
- Always include type hints for function parameters and return types
- Handle exceptions properly with meaningful error messages
- Use i18n library `_()` functions for translatable strings
- Create or update PHPUnit test files for new/updated classes

### CI/CD
The project uses GitHub Actions for continuous integration:
- Validates composer.json and composer.lock
- Installs dependencies with caching
- Ready for test suite integration (currently commented out)

### File Structure
```
src/AboParser/          # Main library code
  AboParser.php         # Main parser class
Example/                # Usage examples
  abo2json.php          # CLI tool for ABO to JSON conversion
  simple_example.php    # Human-readable example script
.github/workflows/      # CI/CD configuration  
tests/                  # Test files with sample ABO data
docs/                   # ABO format documentation (PDF)
```

## Examples and Usage

### Command Line Tools
```bash
# Convert ABO to JSON
./Example/abo2json.php --abofile data.abo --jsonfile output.json

# Human-readable parsing example
./Example/simple_example.php tests/sample.abo
```

### Library Usage
```php
use SpojeNet\AboParser\AboParser;

$parser = new AboParser();
$data = $parser->parseFile('bank_statement.abo');
// Access $data['statements'] and $data['transactions']
```

## Important Notes

### Project Status
The ABO parser is fully functional with:
- Complete ABO format parsing for 074 and 075 records
- Command-line tools for converting ABO to JSON
- Proper character encoding support for Czech/Slovak text
- Comprehensive documentation and examples

### Token Management
The Makefile includes token refresh functionality for CSAS (Czech bank) integration, suggesting this may be related to financial data processing.
