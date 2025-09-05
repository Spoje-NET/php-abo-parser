#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Simple ABO Parser Example
 *
 * This example demonstrates basic usage of the ABO Parser library
 *
 * https://github.com/Spoje-NET/php-abo-parser
 *
 * (c) Spoje.Net IT s.r.o. <https://spojenet.cz>
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SpojeNet\AboParser\AboParser;

// Check if file path is provided
if ($argc < 2) {
    echo "Usage: php simple_example.php <abo-file>\n";
    echo "Example: php simple_example.php ../tests/27_CZ8408000000000002122722_06029396af87517e_CZK_2025-08-21.abo-standard\n";
    exit(1);
}

$filePath = $argv[1];

try {
    // Create parser instance
    $parser = new AboParser();
    
    // Parse the ABO file
    echo "Parsing ABO file: {$filePath}\n";
    $data = $parser->parseFile($filePath);
    
    // Display summary
    echo "\n=== PARSING RESULTS ===\n";
    echo "Format Version: " . ($data['format_version'] ?? 'unknown') . "\n";
    echo "Account Statements: " . count($data['statements']) . "\n";
    echo "Transactions: " . count($data['transactions']) . "\n";
    echo "Raw Records: " . count($data['raw_records']) . "\n";
    
    // Display account statements
    if (!empty($data['statements'])) {
        echo "\n=== ACCOUNT STATEMENTS ===\n";
        foreach ($data['statements'] as $i => $statement) {
            echo "Statement #" . ($i + 1) . ":\n";
            echo "  Account Number: " . $statement['account_number'] . "\n";
            echo "  Account Name: " . $statement['account_name'] . "\n";
            echo "  Statement Number: " . $statement['statement_number'] . "\n";
            echo "  Accounting Date: " . $statement['accounting_date'] . "\n";
            echo "  Old Balance: " . $statement['old_balance'] . " " . $statement['old_balance_sign'] . "\n";
            echo "  New Balance: " . $statement['new_balance'] . " " . $statement['new_balance_sign'] . "\n";
            echo "  Debit Turnover: " . $statement['debit_turnover'] . " " . $statement['debit_turnover_sign'] . "\n";
            echo "  Credit Turnover: " . $statement['credit_turnover'] . " " . $statement['credit_turnover_sign'] . "\n";
            echo "\n";
        }
    }
    
    // Display transactions
    if (!empty($data['transactions'])) {
        echo "=== TRANSACTIONS ===\n";
        foreach ($data['transactions'] as $i => $transaction) {
            echo "Transaction #" . ($i + 1) . ":\n";
            echo "  Amount: " . $transaction['amount'] . "\n";
            echo "  Counter Account: " . $transaction['counter_account'] . "\n";
            echo "  Document Number: " . $transaction['document_number'] . "\n";
            echo "  Variable Symbol: " . $transaction['variable_symbol'] . "\n";
            echo "  Constant Symbol: " . $transaction['constant_symbol'] . "\n";
            echo "  Specific Symbol: " . $transaction['specific_symbol'] . "\n";
            echo "  Valuation Date: " . $transaction['valuation_date'] . "\n";
            echo "  Accounting Code: " . $transaction['accounting_code'] . "\n";
            echo "  Data Type: " . $transaction['data_type'] . "\n";
            
            if (!empty($transaction['additional_info'])) {
                echo "  Additional Info: " . $transaction['additional_info'] . "\n";
            }
            echo "\n";
        }
    }
    
    // Optionally save to JSON file
    if ($argc > 2) {
        $jsonFile = $argv[2];
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($jsonFile, $jsonData);
        echo "JSON data saved to: {$jsonFile}\n";
    }
    
    echo "\nParsing completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
