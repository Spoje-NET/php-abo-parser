<?php

declare(strict_types=1);

/**
 * This file is part of the PohodaRaiffeisenbank package
 *
 * https://github.com/Spoje-NET/php-abo-parser
 *
 * (c) Spoje.Net IT s.r.o. <https://spojenet.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SpojeNet\AboParser;

/**
 * ABO Format Parser for Czechoslovak banking format.
 *
 * Parses ABO format files containing bank statements and transactions.
 * Supports both basic and extended ABO formats with automatic detection.
 */
class AboParser
{
    public const FORMAT_BASIC = 'basic';
    public const FORMAT_EXTENDED = 'extended';

    /**
     * @var string default input encoding for ABO files (Czech: Windows-1250)
     */
    private string $inputEncoding = 'Windows-1250';

    /**
     * @var bool Whether to convert input encoding. Default: true.
     */
    private bool $convertEncoding = true;
    private string $detectedFormat = self::FORMAT_BASIC;
    /**
     * Set input encoding for ABO file parsing.
     *
     * @param string $encoding Encoding name (e.g., 'Windows-1250', 'UTF-8').
     */
    public function setInputEncoding(string $encoding): void
    {
        // Always use Windows-1250 for Czech ABO files
        $this->inputEncoding = 'Windows-1250';
    }

    /**
     * Enable or disable input encoding conversion.
     *
     * @param bool $enabled true to enable, false to disable
     */
    public function setConvertEncoding(bool $enabled): void
    {
        $this->convertEncoding = $enabled;
    }

    /**
     * Parse ABO file from file path.
     *
     * @param string $filePath Path to the ABO file
     *
     * @throws \InvalidArgumentException When file doesn't exist
     *
     * @return array<string, mixed> Parsed data structure
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File not found: '.$filePath);
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException('Unable to read file: '.$filePath);
        }

        // Strip UTF-8 BOM if present (some generators add it), because it would skew fixed-width byte offsets.
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }

        // Parse in original single-byte encoding to keep fixed-width byte offsets correct,
        // then convert individual string values to UTF-8 recursively.
        $parsed = $this->parse($content);

        return self::convertTreeToUtf8($parsed);
    }

    /**
     * Parse ABO format content.
     *
     * @param string $input ABO format content
     *
     * @return array<string, mixed> Parsed data structure
     */
    /**
     * Parse ABO format content.
     *
     * @param string $input ABO format content
     *
     * @return array<string, mixed> Parsed data structure
     */
    public function parse(string $input): array
    {
        $input = $this->convertInputEncoding($input);
        $lines = explode("\n", $input);
        $this->detectedFormat = self::detectFormat($lines);

        $data = [
            'format_version' => $this->detectedFormat,
            'statements' => [],
            'transactions' => [],
            'raw_records' => [],
        ];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Get record type (first 3 characters)
            $recordType = substr($line, 0, 3);
            $data['raw_records'][] = [
                'line' => $lineNumber + 1,
                'type' => $recordType,
                'content' => $line,
            ];

            switch ($recordType) {
                case '074':
                    $data['statements'][] = self::parseAccountStatement($line);

                    break;
                case '075':
                    $data['transactions'][] = $this->parseTransaction($line);

                    break;

                default:
                    // Unknown record type, store as raw
                    break;
            }
        }

        return $data;
    }

    /**
     * Get the detected format version.
     *
     * @return string Format version
     */
    public function getDetectedFormat(): string
    {
        return $this->detectedFormat;
    }

    /**
     * Convert input string to UTF-8 if encoding conversion is enabled.
     *
     * @param string $input raw input string
     *
     * @throws \RuntimeException if conversion fails
     *
     * @return string converted string (UTF-8 or original)
     */
    protected function convertInputEncoding(string $input): string
    {
        if ($this->convertEncoding && $this->inputEncoding !== 'UTF-8') {
            $converted = @iconv($this->inputEncoding, 'UTF-8//IGNORE', $input);

            if ($converted === false || $converted === null) {
                throw new \RuntimeException(
                    _(sprintf('Failed to convert input encoding from %s to UTF-8.', $this->inputEncoding)),
                );
            }

            return $converted;
        }

        return $input;
    }

    /**
     * Detect ABO format version by analyzing record lengths.
     *
     * @param array<string> $lines Input lines
     *
     * @return string Format version (basic or extended)
     */
    private static function detectFormat(array $lines): string
    {
        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Check if it's a 075 record (transaction)
            if (substr($line, 0, 3) === '075') {
                $lineLength = \strlen($line);

                // Basic format 075 records are around 128 characters
                // Extended format 075 records are much longer (1700+ characters)
                if ($lineLength > 500) {
                    return self::FORMAT_EXTENDED;
                }

                // If we find any basic-length 075 record, assume basic format
                return self::FORMAT_BASIC;
            }
        }

        // Default to basic if no 075 records found
        return self::FORMAT_BASIC;
    }

    /**
     * Parse account statement record (074).
     *
     * @param string $line Raw line data
     *
     * @return array<string, mixed> Parsed statement data
     */
    private static function parseAccountStatement(string $line): array
    {
        // According to documentation: 074 record structure
        // Positions: 074|account(16)|name(20)|old_date(6)|old_bal(14)|old_sign(1)|new_bal(14)|new_sign(1)|debit(14)|debit_sign(1)|credit(14)|credit_sign(1)|stmt_num(3)|acc_date(6)|filler(14)
        return [
            'record_type' => '074',
            'account_number' => trim(substr($line, 3, 16)),
            'account_name' => trim(substr($line, 19, 20)),
            'old_balance_date' => self::parseDate(substr($line, 41, 6)),
            'old_balance' => self::parseAmount(substr($line, 47, 14)),
            'old_balance_sign' => substr($line, 61, 1),
            'new_balance' => self::parseAmount(substr($line, 62, 14)),
            'new_balance_sign' => substr($line, 76, 1),
            'debit_turnover' => self::parseAmount(substr($line, 77, 14)),
            'debit_turnover_sign' => substr($line, 91, 1),
            'credit_turnover' => self::parseAmount(substr($line, 92, 14)),
            'credit_turnover_sign' => substr($line, 106, 1),
            'statement_number' => trim(substr($line, 107, 3)),
            'accounting_date' => self::parseDate(substr($line, 110, 6)),
            'raw_line' => $line,
        ];
    }

    /**
     * Parse transaction record (075).
     *
     * @param string $line Raw line data
     *
     * @return array<string, mixed> Parsed transaction data
     */
    private function parseTransaction(string $line): array
    {
        if ($this->detectedFormat === self::FORMAT_EXTENDED) {
            return self::parseExtendedTransaction($line);
        }

        return self::parseBasicTransaction($line);
    }

    /**
     * Parse basic format transaction record (075).
     *
     * @param string $line Raw line data
     *
     * @return array<string, mixed> Parsed transaction data
     */
    private static function parseBasicTransaction(string $line): array
    {
        // According to basic ABO documentation: 075 record structure
        return [
            'record_type' => '075',
            'format' => self::FORMAT_BASIC,
            'account_number' => trim(substr($line, 3, 16)),
            'counter_account' => trim(substr($line, 19, 16)),
            'document_number' => trim(substr($line, 35, 13)),
            'amount' => self::parseAmount(substr($line, 48, 12)),
            'accounting_code' => trim(substr($line, 60, 1)),
            'variable_symbol' => trim(substr($line, 61, 10)),
            'constant_symbol' => trim(substr($line, 71, 10)),
            'specific_symbol' => trim(substr($line, 81, 10)),
            'valuation_date' => self::parseDate(substr($line, 91, 6)),
            'additional_info' => trim(substr($line, 97, 20)),
            'change_code' => trim(substr($line, 117, 1)),
            'data_type' => trim(substr($line, 118, 4)),
            'due_date' => self::parseDate(substr($line, 122, 6)),
            'raw_line' => $line,
        ];
    }

    /**
     * Parse extended format transaction record (075).
     *
     * @param string $line Raw line data
     *
     * @return array<string, mixed> Parsed transaction data
     */
    private static function parseExtendedTransaction(string $line): array
    {
        // Start with basic fields (same positions)
        $transaction = self::parseBasicTransaction($line);
        $transaction['format'] = self::FORMAT_EXTENDED;

        // Add extended format specific fields (positions based on extended documentation)
        $pos = 128; // Start after basic fields

        // Extract extended fields safely (check line length to avoid errors)
        $lineLength = \strlen($line);

        if ($lineLength > $pos) {
            $transaction['message_for_recipient'] = trim(substr($line, $pos, 35));
            $pos += 35;
        }

        if ($lineLength > $pos) {
            $transaction['message_for_recipient_2'] = trim(substr($line, $pos, 35));
            $pos += 35;
        }

        if ($lineLength > $pos) {
            $transaction['message_for_recipient_3'] = trim(substr($line, $pos, 35));
            $pos += 35;
        }

        if ($lineLength > $pos) {
            $transaction['message_for_recipient_4'] = trim(substr($line, $pos, 35));
            $pos += 35;
        }

        if ($lineLength > $pos) {
            $transaction['message_for_sender'] = trim(substr($line, $pos, 35));
            $pos += 35;
        }

        if ($lineLength > $pos + 6) {
            $transaction['debited_date'] = self::parseDate(substr($line, $pos, 6));
            $pos += 6;
        }

        if ($lineLength > $pos + 25) {
            $transaction['item_description'] = trim(substr($line, $pos, 25));
            $pos += 25;
        }

        if ($lineLength > $pos + 16) {
            $transaction['identification_reference'] = trim(substr($line, $pos, 16));
            $pos += 16;
        }

        if ($lineLength > $pos + 15) {
            $transaction['amount_iso'] = self::parseAmount(substr($line, $pos, 15));
            $pos += 15;
        }

        if ($lineLength > $pos + 3) {
            $transaction['currency_iso'] = trim(substr($line, $pos, 3));
            $pos += 3;
        }

        if ($lineLength > $pos + 35) {
            $transaction['counter_account_name'] = trim(substr($line, $pos, 35));
            $pos += 35;
        }

        // Add more extended fields as needed...
        // Note: Due to the complexity of the extended format with 49 fields,
        // we're implementing the most commonly used ones here.

        return $transaction;
    }

    /**
     * Parse date in ddmmrr format.
     *
     * @param string $dateStr Date string in ddmmrr format
     *
     * @return null|string Formatted date or null if invalid
     */
    private static function parseDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);

        if (\strlen($dateStr) !== 6 || !ctype_digit($dateStr)) {
            return null;
        }

        $day = substr($dateStr, 0, 2);
        $month = substr($dateStr, 2, 2);
        $year = substr($dateStr, 4, 2);

        // Convert 2-digit year to 4-digit (assuming 2000s)
        $fullYear = '20'.$year;

        // Basic validation
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            return null;
        }

        return $fullYear.'-'.$month.'-'.$day;
    }

    /**
     * Parse amount (in haléře/cents, last two digits are decimal).
     *
     * @param string $amountStr Amount string
     *
     * @return float Parsed amount
     */
    private static function parseAmount(string $amountStr): float
    {
        $amountStr = trim($amountStr);

        // Handle empty strings
        if (empty($amountStr)) {
            return 0.0;
        }

        // Remove leading zeros and check if all digits
        $amountStr = ltrim($amountStr, '0');

        if (empty($amountStr)) {
            return 0.0;
        }

        if (!ctype_digit($amountStr)) {
            return 0.0;
        }

        // Last two digits are cents/haléře
        $amount = (int) $amountStr;

        return $amount / 100.0;
    }

    /**
     * Recursively convert all strings in the parsed structure to UTF-8, assuming Windows-1250 for non-UTF8 input.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function convertTreeToUtf8($value)
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::convertTreeToUtf8($v);
            }

            return $value;
        }

        if (\is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $converted = @iconv('Windows-1250', 'UTF-8', $value);

                if ($converted !== false && $converted !== null) {
                    return $converted;
                }
            }
        }

        return $value;
    }
}
