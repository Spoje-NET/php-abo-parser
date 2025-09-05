<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpojeNet\AboParser\AboParser;

/**
 * Unit tests for AboParser class
 */
class AboParserTest extends TestCase
{
    private AboParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AboParser();
    }

    /**
     * Test format detection with basic format data
     */
    public function testDetectsBasicFormat(): void
    {
        $basicData = "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825";
        
        $result = $this->parser->parse($basicData);
        
        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        $this->assertEquals(AboParser::FORMAT_BASIC, $this->parser->getDetectedFormat());
    }

    /**
     * Test format detection with extended format data
     */
    public function testDetectsExtendedFormat(): void
    {
        $extendedData = "0750000000001234567000000000987654300012345678900000000100000101234567890123456789012345678902108252025-08-21                Payment for servicesAdditional payment informationFurther detailsExtra payment notesInternal referenceREF123456789098765ABCDEFGHIJKLMCZKEXTENDED RECIPIENT BANK NAME       Exchange rate informationMore extended fieldsTransaction categoryFee informationSWIFT code dataSEPA informationAdditional SEPA dataMore SEPA detailsCharge informationDetailed charge infoFee breakdownOriginal currency amountMT103 referenceBank reference numberSEPA field 1SEPA field 2SEPA field 3Charge type descriptionDetailed charge description 1Detailed charge description 2Sender note 1Sender note 2Sender note 3Sender note 4";
        
        $result = $this->parser->parse($extendedData);
        
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $result['format_version']);
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $this->parser->getDetectedFormat());
    }

    /**
     * Test parsing empty input
     */
    public function testParseEmptyInput(): void
    {
        $result = $this->parser->parse("");
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('format_version', $result);
        $this->assertArrayHasKey('statements', $result);
        $this->assertArrayHasKey('transactions', $result);
        $this->assertArrayHasKey('raw_records', $result);
        
        $this->assertEmpty($result['statements']);
        $this->assertEmpty($result['transactions']);
        $this->assertEmpty($result['raw_records']);
    }

    /**
     * Test parsing basic format account statement (074 record)
     */
    public function testParseBasicAccountStatement(): void
    {
        $data = "0740000000001234567Test Account Name   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        
        $statement = $result['statements'][0];
        $this->assertEquals('074', $statement['record_type']);
        $this->assertEquals('0000000001234567', $statement['account_number']);
        $this->assertEquals('Test Account Name', $statement['account_name']);
        $this->assertEquals('2025-08-20', $statement['old_balance_date']);
        $this->assertEquals(10000.0, $statement['old_balance']);
        $this->assertEquals('+', $statement['old_balance_sign']);
        $this->assertEquals(9500.0, $statement['new_balance']);
        $this->assertEquals('+', $statement['new_balance_sign']);
        $this->assertEquals('2025-08-21', $statement['accounting_date']);
    }

    /**
     * Test parsing basic format transaction (075 record)
     */
    public function testParseBasicTransaction(): void
    {
        $data = "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825";
        
        $result = $this->parser->parse($data);
        
        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        $this->assertCount(1, $result['transactions']);
        
        $transaction = $result['transactions'][0];
        $this->assertEquals('075', $transaction['record_type']);
        $this->assertEquals(AboParser::FORMAT_BASIC, $transaction['format']);
        $this->assertEquals('0000000001234567', $transaction['account_number']);
        $this->assertEquals('0000000009876543', $transaction['counter_account']);
        $this->assertEquals('0001234567890', $transaction['document_number']);
        $this->assertEquals(50.0, $transaction['amount']);
        $this->assertEquals('1', $transaction['accounting_code']);
        $this->assertEquals('0123456789', $transaction['variable_symbol']);
        $this->assertEquals('2025-08-21', $transaction['valuation_date']);
        $this->assertEquals('2025-08-21', $transaction['due_date']);
    }

    /**
     * Test parsing extended format transaction
     */
    public function testParseExtendedTransaction(): void
    {
        $extendedData = "0750000000001234567000000000987654300012345678900000000100000101234567890123456789012345678902108252025-08-21                Payment for servicesAdditional payment informationFurther detailsExtra payment notesInternal referenceREF123456789098765ABCDEFGHIJKLMCZKEXTENDED RECIPIENT BANK NAME       ";
        
        $result = $this->parser->parse($extendedData);
        
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $result['format_version']);
        $this->assertCount(1, $result['transactions']);
        
        $transaction = $result['transactions'][0];
        $this->assertEquals('075', $transaction['record_type']);
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $transaction['format']);
        $this->assertEquals('0000000001234567', $transaction['account_number']);
        $this->assertEquals(1000.0, $transaction['amount']);
        
        // Test extended format specific fields
        $this->assertArrayHasKey('message_for_recipient', $transaction);
        $this->assertArrayHasKey('message_for_recipient_2', $transaction);
        $this->assertArrayHasKey('message_for_sender', $transaction);
        $this->assertArrayHasKey('identification_reference', $transaction);
        $this->assertArrayHasKey('currency_iso', $transaction);
    }

    /**
     * Test date parsing
     */
    public function testDateParsing(): void
    {
        $data = "0740000000001234567Test Account Name   210825000000010000000+000000009500000+000000000500000+000000000000000+001220825";
        
        $result = $this->parser->parse($data);
        $statement = $result['statements'][0];
        
        $this->assertEquals('2025-08-21', $statement['old_balance_date']);
        $this->assertEquals('2025-08-22', $statement['accounting_date']);
    }

    /**
     * Test amount parsing (haléře/cents)
     */
    public function testAmountParsing(): void
    {
        $data = "0750000000001234567000000000987654300012345678900000001234567100123456789012345678901234567890210825                    01101210825";
        
        $result = $this->parser->parse($data);
        $transaction = $result['transactions'][0];
        
        // Amount 1234567 should be parsed as 12345.67
        $this->assertEquals(12345.67, $transaction['amount']);
    }

    /**
     * Test parsing multiple records
     */
    public function testParseMultipleRecords(): void
    {
        $data = "0740000000001234567Test Account Name   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825              \n";
        $data .= "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825\n";
        $data .= "0750000000001234567000000001111222200012345678910000000150000200987654321098765432100000000000210825                    01101210825";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        $this->assertCount(2, $result['transactions']);
        $this->assertCount(3, $result['raw_records']);
    }

    /**
     * Test unknown record types are stored as raw records
     */
    public function testUnknownRecordTypes(): void
    {
        $data = "999unknown_record_type\n074invalid_short_record";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(0, $result['statements']);
        $this->assertCount(0, $result['transactions']);
        $this->assertCount(2, $result['raw_records']);
        
        $this->assertEquals('999', $result['raw_records'][0]['type']);
        $this->assertEquals('074', $result['raw_records'][1]['type']);
    }

    /**
     * Test file parsing with non-existent file
     */
    public function testParseNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');
        
        $this->parser->parseFile('/non/existent/file.abo');
    }

    /**
     * Test constants are defined correctly
     */
    public function testConstants(): void
    {
        $this->assertEquals('basic', AboParser::FORMAT_BASIC);
        $this->assertEquals('extended', AboParser::FORMAT_EXTENDED);
    }

    /**
     * Test default format detection with no 075 records
     */
    public function testDefaultFormatDetection(): void
    {
        $data = "0740000000001234567Test Account Name   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825";
        
        $result = $this->parser->parse($data);
        
        // Should default to basic format when no 075 records are found
        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
    }

    /**
     * Test whitespace and empty lines are handled correctly
     */
    public function testWhitespaceHandling(): void
    {
        $data = "\n\n   \n0740000000001234567Test Account Name   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825\n\n  \n";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        $this->assertCount(1, $result['raw_records']); // Only non-empty lines should be recorded
    }
}
