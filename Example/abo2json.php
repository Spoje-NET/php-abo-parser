#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ABO to JSON Converter
 *
 * https://github.com/Spoje-NET/php-abo-parser
 *
 * (c) Spoje.Net IT s.r.o. <https://spojenet.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SpojeNet\AboParser\AboParser;

\define('APP_NAME', 'abo2json');

/**
 * Display usage information
 */
function showUsage(): void
{
    echo "Usage: " . basename($_SERVER['argv'][0]) . " [OPTIONS]\n";
    echo "\nOptions:\n";
    echo "  --abofile <file>    Path to ABO file to parse\n";
    echo "  --jsonfile <file>   Output JSON file (optional, defaults to stdout)\n";
    echo "  --help, -h          Show this help message\n";
    echo "\nExamples:\n";
    echo "  " . basename($_SERVER['argv'][0]) . " --abofile data.abo\n";
    echo "  " . basename($_SERVER['argv'][0]) . " --abofile data.abo --jsonfile output.json\n";
    echo "  cat data.abo | " . basename($_SERVER['argv'][0]) . "\n";
    echo "\nIf no --abofile is provided, the script reads from stdin.\n";
}

/**
 * Parse command line arguments
 * 
 * @param array<string> $argv Command line arguments
 * @return array<string, mixed> Parsed arguments
 */
function parseArguments(array $argv): array
{
    $options = [
        'abofile' => null,
        'jsonfile' => null,
        'help' => false
    ];
    
    $i = 1;
    while ($i < count($argv)) {
        switch ($argv[$i]) {
            case '--abofile':
                if (!isset($argv[$i + 1])) {
                    fprintf(STDERR, "Error: --abofile requires a file path\n");
                    exit(1);
                }
                $options['abofile'] = $argv[$i + 1];
                $i += 2;
                break;
                
            case '--jsonfile':
                if (!isset($argv[$i + 1])) {
                    fprintf(STDERR, "Error: --jsonfile requires a file path\n");
                    exit(1);
                }
                $options['jsonfile'] = $argv[$i + 1];
                $i += 2;
                break;
                
            case '--help':
            case '-h':
                $options['help'] = true;
                $i++;
                break;
                
            default:
                fprintf(STDERR, "Error: Unknown option '%s'\n", $argv[$i]);
                showUsage();
                exit(1);
        }
    }
    
    return $options;
}

/**
 * Read input data from file or stdin
 * 
 * @param string|null $filePath File path or null for stdin
 * @return string Input data
 */
function readInput(?string $filePath): string
{
    if ($filePath !== null) {
        if (!file_exists($filePath)) {
            fprintf(STDERR, "Error: File '%s' does not exist\n", $filePath);
            exit(1);
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            fprintf(STDERR, "Error: Unable to read file '%s'\n", $filePath);
            exit(1);
        }
        
        return $content;
    }
    
    // Read from stdin
    $content = '';
    while (!feof(STDIN)) {
        $chunk = fread(STDIN, 8192);
        if ($chunk === false) {
            fprintf(STDERR, "Error: Unable to read from stdin\n");
            exit(1);
        }
        $content .= $chunk;
    }
    
    return $content;
}

/**
 * Write output to file or stdout
 * 
 * @param string $data Data to write
 * @param string|null $filePath Output file path or null for stdout
 */
function writeOutput(string $data, ?string $filePath): void
{
    if ($filePath !== null) {
        $result = file_put_contents($filePath, $data);
        if ($result === false) {
            fprintf(STDERR, "Error: Unable to write to file '%s'\n", $filePath);
            exit(1);
        }
        fprintf(STDERR, "Output written to: %s\n", $filePath);
    } else {
        echo $data;
    }
}

/**
 * Clean data for JSON encoding by ensuring all strings are valid UTF-8
 * 
 * @param mixed $data Data to clean
 * @return mixed Cleaned data
 */
function cleanDataForJson($data)
{
    if (is_array($data)) {
        return array_map('cleanDataForJson', $data);
    } elseif (is_string($data)) {
        // Remove or replace invalid UTF-8 sequences
        $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        // Remove non-printable characters except common whitespace
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);
    }
    return $data;
}

// Main execution
try {
    $options = parseArguments($_SERVER['argv']);
    
    if ($options['help']) {
        showUsage();
        exit(0);
    }
    
    // Read input
    $input = readInput($options['abofile']);
    
    if (empty(trim($input))) {
        fprintf(STDERR, "Error: No input data provided\n");
        exit(1);
    }
    
    // Convert to UTF-8 if needed (force conversion from common encodings)
    // ABO files are often in Windows-1250 or ISO-8859-2 encoding
    $input = mb_convert_encoding($input, 'UTF-8', 'auto');
    
    // Parse ABO data
    $parser = new AboParser();
    $parsedData = $parser->parse($input);
    
    // Clean data for JSON encoding
    $cleanedData = cleanDataForJson($parsedData);
    
    // Convert to JSON with pretty printing
    $jsonData = json_encode($cleanedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($jsonData === false) {
        $jsonError = json_last_error_msg();
        fprintf(STDERR, "Error: Unable to encode data to JSON: %s\n", $jsonError);
        // Debug: show some sample data
        fprintf(STDERR, "Debug: Sample parsed data structure:\n");
        fprintf(STDERR, "Statements count: %d\n", count($parsedData['statements']));
        fprintf(STDERR, "Transactions count: %d\n", count($parsedData['transactions']));
        fprintf(STDERR, "Raw records count: %d\n", count($parsedData['raw_records']));
        exit(1);
    }
    
    // Write output
    writeOutput($jsonData . "\n", $options['jsonfile']);
    
} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

