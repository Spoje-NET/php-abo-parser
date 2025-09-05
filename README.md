# PHP ABO Parser

A PHP library for parsing Czechoslovak ABO (Account Bank Operations) format files commonly used by Czech and Slovak banks for financial data exchange.

[![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Build Status](https://img.shields.io/github/actions/workflow/status/Spoje-NET/php-abo-parser/ci.yml?branch=main)](https://github.com/Spoje-NET/php-abo-parser/actions)
[![Coverage Status](https://img.shields.io/codecov/c/github/Spoje-NET/php-abo-parser?style=flat-square)](https://codecov.io/gh/Spoje-NET/php-abo-parser)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%206-blueviolet.svg)](https://phpstan.org/)
[![Downloads](https://img.shields.io/packagist/dt/spojenet/abo-parser.svg)](https://packagist.org/packages/spojenet/abo-parser)
[![Last Commit](https://img.shields.io/github/last-commit/Spoje-NET/php-abo-parser.svg)](https://github.com/Spoje-NET/php-abo-parser/commits/main)


## Features

- **Dual Format Support**: Automatically detects and parses both basic and extended ABO formats
- **Complete Record Parsing**: Handles account statements (074 records) and transaction records (075 records)
- **Format Detection**: Automatically identifies which ABO format version is being used
- **Extended Format Fields**: Supports additional fields in extended format (message fields, ISO currency info, SWIFT codes, etc.)
- **Multiple Input Methods**: Read from files or stdin
- **Flexible Output**: Output to JSON format with pretty printing
- **Character Encoding Support**: Handles Czech/Slovak character encodings (Windows-1250, ISO-8859-2)
- **Command Line Tool**: Ready-to-use CLI script for ABO to JSON conversion
- **Type Safe**: Full PHP 8.4+ type hints and strict mode
- **PSR-4 Autoloading**: Composer compatible

## Installation

### Via Composer

```bash
composer require spojenet/abo-parser
```

### Manual Installation

```bash
git clone https://github.com/Spoje-NET/php-abo-parser.git
cd php-abo-parser
composer install
```

## Usage

### Command Line Tool

The library includes a ready-to-use command line tool for converting ABO files to JSON:

```bash
# Show help
./Example/abo2json.php --help

# Convert ABO file to JSON (output to stdout)
./Example/abo2json.php --abofile data.abo

# Convert ABO file and save to JSON file
./Example/abo2json.php --abofile data.abo --jsonfile output.json

# Read from stdin and output to stdout
cat data.abo | ./Example/abo2json.php

# Read from stdin and save to file
cat data.abo | ./Example/abo2json.php --jsonfile output.json
```

#### Simple Example Script

For a more detailed demonstration, use the included simple example:

```bash
# Run the simple example
./Example/simple_example.php path/to/your/file.abo

# Run and save JSON output
./Example/simple_example.php path/to/your/file.abo output.json
```

This script provides a human-readable summary of the parsed data.

### PHP Library API

#### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use SpojeNet\AboParser\AboParser;

// Parse from file
$parser = new AboParser();
$data = $parser->parseFile('path/to/file.abo');

// Parse from string
$aboContent = file_get_contents('path/to/file.abo');
$data = $parser->parse($aboContent);

// Access parsed data
echo "Found " . count($data['statements']) . " account statements\n";
echo "Found " . count($data['transactions']) . " transactions\n";

// Convert to JSON
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo $json;
```

#### Advanced Usage

```php
<?php
use SpojeNet\AboParser\AboParser;

$parser = new AboParser();
$data = $parser->parseFile('bank_statement.abo');

// Process account statements (074 records)
foreach ($data['statements'] as $statement) {
    echo "Account: " . $statement['account_number'] . "\n";
    echo "Account Name: " . $statement['account_name'] . "\n";
    echo "Old Balance: " . $statement['old_balance'] . "\n";
    echo "New Balance: " . $statement['new_balance'] . "\n";
    echo "Statement Date: " . $statement['accounting_date'] . "\n\n";
}

// Process transactions (075 records)
foreach ($data['transactions'] as $transaction) {
    echo "Transaction Amount: " . $transaction['amount'] . "\n";
    echo "Counter Account: " . $transaction['counter_account'] . "\n";
    echo "Variable Symbol: " . $transaction['variable_symbol'] . "\n";
    echo "Date: " . $transaction['valuation_date'] . "\n\n";
}

// Access raw records for debugging
foreach ($data['raw_records'] as $record) {
    echo "Line " . $record['line'] . " (Type: " . $record['type'] . "): " . $record['content'] . "\n";
}
```

## Data Structure

The parser returns an array with three main sections:

### Parsed Data Structure

```php
[
    'format_version' => 'basic|extended',  // Detected ABO format version
    'statements' => [...],                 // Parsed account statements (074 records)
    'transactions' => [...],               // Parsed transactions (075 records)
    'raw_records' => [...]                // Raw line data for debugging
]
```

### Account Statement (074 Record)

```php
[
    'record_type' => '074',
    'account_number' => '0000000002122722',
    'account_name' => 'Account Holder Name',
    'old_balance_date' => '2025-08-20',
    'old_balance' => 99474.70,
    'old_balance_sign' => '+',
    'new_balance' => 93201.15,
    'new_balance_sign' => '+',
    'debit_turnover' => 6273.55,
    'debit_turnover_sign' => '+',
    'credit_turnover' => 0.00,
    'credit_turnover_sign' => '+',
    'statement_number' => '027',
    'accounting_date' => '2025-08-21',
    'raw_line' => '074...'
]
```

### Transaction (075 Record)

#### Basic Format
```php
[
    'record_type' => '075',
    'format' => 'basic',
    'account_number' => '0000000002122722',
    'counter_account' => '0000000100785108',
    'document_number' => '0001865283798',
    'amount' => 110.00,
    'accounting_code' => '1',
    'variable_symbol' => '5174658167',
    'constant_symbol' => '0003000308',
    'specific_symbol' => '0000000000',
    'valuation_date' => '2025-08-21',
    'additional_info' => '',
    'change_code' => '0',
    'data_type' => '1101',
    'due_date' => '2025-08-21',
    'raw_line' => '075...'
]
```

#### Extended Format (includes all basic fields plus:)
```php
[
    // ... all basic fields ...
    'format' => 'extended',
    'message_for_recipient' => 'Payment description',
    'message_for_recipient_2' => 'Additional info line 2',
    'message_for_recipient_3' => 'Additional info line 3', 
    'message_for_recipient_4' => 'Additional info line 4',
    'message_for_sender' => 'Internal note',
    'debited_date' => '2025-08-21',
    'item_description' => 'Transaction type',
    'identification_reference' => 'REF123456789',
    'amount_iso' => 110.00,
    'currency_iso' => 'CZK',
    'counter_account_name' => 'Recipient Bank Name',
    // ... and many more extended fields
]
```

## ABO Format Specification

The ABO format is a fixed-width text format used in Czech and Slovak banking systems. This library supports:

- **074 Records**: Account statements with balance information
- **075 Records**: Individual transaction records
- **Character Encodings**: Windows-1250, ISO-8859-2, UTF-8
- **Date Format**: ddmmrr (day-month-year, 2-digit year)
- **Amount Format**: Integer values in haléře/cents (last 2 digits are decimal)

### ABO Format Versions

There are two versions of the ABO format:

#### Basic ABO Format
- **075 Record Length**: ~128 characters
- **Fields**: 15 fields with essential transaction information
- **Usage**: Standard for most banking applications
- **Fields Include**: Account numbers, amounts, symbols, dates, basic descriptions

#### Extended ABO Format  
- **075 Record Length**: 1700+ characters
- **Fields**: 49 fields with comprehensive transaction details
- **Usage**: Advanced banking systems requiring detailed transaction information
- **Additional Fields Include**:
  - Multiple message fields for recipients and senders
  - ISO currency information and exchange rates
  - SWIFT bank codes and international references
  - SEPA payment information
  - Detailed fee and charge information
  - Multiple transaction description fields

**Format Detection**: The parser automatically detects which format is being used by analyzing the length of 075 records. No manual configuration is required.

## Requirements

- **PHP**: 8.4 or higher
- **Extensions**: mbstring (for character encoding conversion)
- **Composer**: For autoloading (recommended)

## Development

### Running Tests

```bash
# Install dev dependencies
composer install

# Run tests
vendor/bin/phpunit tests
# OR
make tests
```

### Code Quality

```bash
# Static analysis
vendor/bin/phpstan analyse --configuration=phpstan-default.neon.dist --memory-limit=-1
# OR
make static-code-analysis

# Code formatting
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose --allow-risky=yes
# OR
make cs
```

### Available Make Commands

```bash
# Show all available commands
make help
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- PSR-12 coding standard
- PHPStan level 6
- All code comments and messages in English
- Type hints for all function parameters and return types
- Comprehensive PHPDoc blocks

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/Spoje-NET/php-abo-parser/issues)
- **Documentation**: This README and inline code documentation
- **Examples**: See the `Example/` directory

## Acknowledgments

- Czech and Slovak banking institutions for ABO format specification
- Contributors and maintainers

<a href="https://spojenet.cz"><img src="spojentetitlogo.svg" alt="SpojeNetIT s.r.o." height="40"></a>
