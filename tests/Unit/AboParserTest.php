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

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpojeNet\AboParser\AboParser;

/**
 * Unit tests for AboParser class.
 */
class AboParserTest extends TestCase
{
    private AboParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AboParser();
    }
    /**
     * Test encoding conversion from Windows-1250 to UTF-8.
     */
    public function testEncodingConversion(): void
    {
        $isoString = iconv('UTF-8', 'ISO-8859-2', '0740000000001234567Česká banka     20082500000009947470+00000009320115+00000000627355+00000000000000+027210825              ');
        $this->parser->setInputEncoding('ISO-8859-2');
        $result = $this->parser->parse($isoString);
        $statement = $result['statements'][0];
        $this->assertStringContainsString('Česká banka', $statement['account_name']);
    }

    /**
     * Test disabling encoding conversion.
     */
    public function testDisableEncodingConversion(): void
    {
        $utf8String = '0740000000001234567Česká banka     20082500000009947470+00000009320115+00000000627355+00000000000000+027210825              ';
        $this->parser->setInputEncoding('UTF-8');
        $this->parser->setConvertEncoding(false);
        $result = $this->parser->parse($utf8String);
        $statement = $result['statements'][0];
        $this->assertStringContainsString('Česká banka', $statement['account_name']);
    }

    /**
     * Test format detection with basic format data.
     */
    public function testDetectsBasicFormat(): void
    {
        $basicData = '0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825';

        $result = $this->parser->parse($basicData);

        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        $this->assertEquals(AboParser::FORMAT_BASIC, $this->parser->getDetectedFormat());
    }

    /**
     * Test format detection with extended format data.
     */
    public function testDetectsExtendedFormat(): void
    {
        $extendedData = '0750000000001234567000000000987654300012345678900000000100000101234567890123456789012345678902108252025-08-21                Payment for servicesAdditional payment informationFurther detailsExtra payment notesInternal referenceREF123456789098765ABCDEFGHIJKLMCZKEXTENDED RECIPIENT BANK NAME       Exchange rate informationMore extended fieldsTransaction categoryFee informationSWIFT code dataSEPA informationAdditional SEPA dataMore SEPA detailsCharge informationDetailed charge infoFee breakdownOriginal currency amountMT103 referenceBank reference numberSEPA field 1SEPA field 2SEPA field 3Charge type descriptionDetailed charge description 1Detailed charge description 2Sender note 1Sender note 2Sender note 3Sender note 4';

        $result = $this->parser->parse($extendedData);

        $this->assertEquals(AboParser::FORMAT_EXTENDED, $result['format_version']);
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $this->parser->getDetectedFormat());
    }

    /**
     * Test parsing empty input.
     */
    public function testParseEmptyInput(): void
    {
        $result = $this->parser->parse('');

        $this->assertArrayHasKey('format_version', $result);
        $this->assertArrayHasKey('statements', $result);
        $this->assertArrayHasKey('transactions', $result);
        $this->assertArrayHasKey('raw_records', $result);

        $this->assertEmpty($result['statements']);
        $this->assertEmpty($result['transactions']);
        $this->assertEmpty($result['raw_records']);
    }

    /**
     * Test parsing basic format account statement (074 record).
     */
    public function testParseBasicAccountStatement(): void
    {
        $data = '0740000000001234567Test Account Name     20082500000009947470+00000009320115+00000000627355+00000000000000+027210825              ';

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['statements']);

        $statement = $result['statements'][0];
        $this->assertEquals('074', $statement['record_type']);
        $this->assertEquals('0000000001234567', $statement['account_number']);
        $this->assertEquals('Test Account Name', $statement['account_name']);
        $this->assertEquals('2025-08-20', $statement['old_balance_date']);
        $this->assertEquals(99474.70, $statement['old_balance']);
        $this->assertEquals('+', $statement['old_balance_sign']);
        $this->assertEquals(93201.15, $statement['new_balance']);
        $this->assertEquals('+', $statement['new_balance_sign']);
        $this->assertEquals('2025-08-21', $statement['accounting_date']);
    }

    /**
     * Test parsing basic format transaction (075 record).
     */
    public function testParseBasicTransaction(): void
    {
        $data = '0750000000002122722000000010078510800018652837980000000110001517465816700030003080000000000210825                    01101210825';

        $result = $this->parser->parse($data);

        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        $this->assertCount(1, $result['transactions']);

        $transaction = $result['transactions'][0];
        $this->assertEquals('075', $transaction['record_type']);
        $this->assertEquals(AboParser::FORMAT_BASIC, $transaction['format']);
        $this->assertEquals('0000000002122722', $transaction['account_number']);
        $this->assertEquals('0000000100785108', $transaction['counter_account']);
        $this->assertEquals('0001865283798', $transaction['document_number']);
        $this->assertEquals(110.0, $transaction['amount']);
        $this->assertEquals('1', $transaction['accounting_code']);
        $this->assertEquals('5174658167', $transaction['variable_symbol']);
        $this->assertEquals('2025-08-21', $transaction['valuation_date']);
        $this->assertEquals('2025-08-21', $transaction['due_date']);
    }

    /**
     * Test parsing extended format transaction.
     */
    public function testParseExtendedTransaction(): void
    {
        $extendedData = '0750000000001234567000000000987654300012345678900000010000001012345678901234567890123456789021082520250821Payment for servicesAdditional payment informationFurther detailsExtra payment notesInternal referenceREF123456789098765ABCDEFGHIJKLMCZKEXTENDED RECIPIENT BANK NAME       Exchange rate informationMore extended fieldsTransaction categoryFee informationSWIFT code dataSEPA informationAdditional SEPA dataMore SEPA detailsCharge informationDetailed charge infoFee breakdownOriginal currency amountMT103 referenceBank reference numberSEPA field 1SEPA field 2SEPA field 3Charge type descriptionDetailed charge description 1Detailed charge description 2Sender note 1Sender note 2Sender note 3Sender note 4';

        $result = $this->parser->parse($extendedData);

        $this->assertEquals(AboParser::FORMAT_EXTENDED, $result['format_version']);
        $this->assertCount(1, $result['transactions']);

        $transaction = $result['transactions'][0];
        $this->assertEquals('075', $transaction['record_type']);
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $transaction['format']);
        $this->assertEquals('0000000001234567', $transaction['account_number']);
        $this->assertEquals(10000.0, $transaction['amount']);

        // Test extended format specific fields
        $this->assertArrayHasKey('message_for_recipient', $transaction);
        $this->assertArrayHasKey('message_for_recipient_2', $transaction);
        $this->assertArrayHasKey('message_for_sender', $transaction);
        $this->assertArrayHasKey('identification_reference', $transaction);
        $this->assertArrayHasKey('currency_iso', $transaction);
    }

    /**
     * Test date parsing.
     */
    public function testDateParsing(): void
    {
        $data = '0740000000001234567Test Account Name     21082500000009947470+00000009320115+00000000627355+00000000000000+027220825              ';

        $result = $this->parser->parse($data);
        $statement = $result['statements'][0];

        $this->assertEquals('2025-08-21', $statement['old_balance_date']);
        $this->assertEquals('2025-08-22', $statement['accounting_date']);
    }

    /**
     * Test amount parsing (haléře/cents).
     */
    public function testAmountParsing(): void
    {
        $data = '0750000000002122722000000010078510800018652837980000012345671517465816700030003080000000000210825                    01101210825';

        $result = $this->parser->parse($data);
        $transaction = $result['transactions'][0];

        // Amount 000001234567 should be parsed as 12345.67
        $this->assertEquals(12345.67, $transaction['amount']);
    }

    /**
     * Test parsing multiple records.
     */
    public function testParseMultipleRecords(): void
    {
        $data = "0740000000001234567Test Account Name   20082500000009947470+00000009320115+00000000627355+00000000000000+027210825              \n";
        $data .= "0750000000001234567000000010078510800018652837980000000110001517465816700030003080000000000210825                    01101210825\n";
        $data .= '0750000000001234567000000023567810700018652837990000001331001002025008000060003080000000000210825                    01101210825';

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['statements']);
        $this->assertCount(2, $result['transactions']);
        $this->assertCount(3, $result['raw_records']);
    }

    /**
     * Test unknown record types are stored as raw records.
     */
    public function testUnknownRecordTypes(): void
    {
        $data = "999unknown_record_type\n074invalid_short_record";

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['statements']); // 074 record will still parse as statement
        $this->assertCount(0, $result['transactions']);
        $this->assertCount(2, $result['raw_records']);

        $this->assertEquals('999', $result['raw_records'][0]['type']);
        $this->assertEquals('074', $result['raw_records'][1]['type']);
    }

    /**
     * Test file parsing with non-existent file.
     */
    public function testParseNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $this->parser->parseFile('/non/existent/file.abo');
    }

    /**
     * Test constants are defined correctly.
     */
    public function testConstants(): void
    {
        $this->assertEquals('basic', AboParser::FORMAT_BASIC);
        $this->assertEquals('extended', AboParser::FORMAT_EXTENDED);
    }

    /**
     * Test default format detection with no 075 records.
     */
    public function testDefaultFormatDetection(): void
    {
        $data = '0740000000001234567Test Account Name   20082500000009947470+00000009320115+00000000627355+00000000000000+027210825              ';

        $result = $this->parser->parse($data);

        // Should default to basic format when no 075 records are found
        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
    }

    /**
     * Test whitespace and empty lines are handled correctly.
     */
    public function testWhitespaceHandling(): void
    {
        $data = "\n\n   \n0740000000001234567Test Account Name   20082500000009947470+00000009320115+00000000627355+00000000000000+027210825              \n\n  \n";

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['statements']);
        $this->assertCount(1, $result['raw_records']); // Only non-empty lines should be recorded
    }
}
