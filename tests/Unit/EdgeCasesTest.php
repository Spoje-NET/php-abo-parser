<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpojeNet\AboParser\AboParser;

/**
 * Edge case and error handling tests
 */
class EdgeCasesTest extends TestCase
{
    private AboParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AboParser();
    }

    /**
     * Test parsing invalid date formats
     */
    public function testInvalidDateFormats(): void
    {
        // Invalid dates should return null
        $data = "074000000000123456732012500000000100000002500000095000003200000005000004100000000000000+001990230"; // 32nd day, 13th month
        
        $result = $this->parser->parse($data);
        $statement = $result['statements'][0];
        
        $this->assertNull($statement['old_balance_date']); // Invalid date
        $this->assertNull($statement['accounting_date']); // Invalid date
    }

    /**
     * Test parsing very long lines (should not crash)
     */
    public function testVeryLongLines(): void
    {
        $longLine = '074' . str_repeat('A', 2000); // Very long line
        
        $result = $this->parser->parse($longLine);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result['raw_records']);
        $this->assertEquals('074', $result['raw_records'][0]['type']);
    }

    /**
     * Test parsing lines with special characters
     */
    public function testSpecialCharacters(): void
    {
        $data = "074000000000123456789ÁČĎÉĚÍŇ ŘŠŤÚŮÝŽ123450825000000010000000+000000009500000+000000000500000+000000000000000+001210825";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        $this->assertStringContainsString('ÁČĎÉĚÍŇ', $result['statements'][0]['account_name']);
    }

    /**
     * Test parsing with boundary amounts (zero, maximum)
     */
    public function testBoundaryAmounts(): void
    {
        // Test with zero amount
        $dataZero = "0750000000001234567000000000987654300012345678900000000000000100123456789012345678901234567890210825                    01101210825";
        $resultZero = $this->parser->parse($dataZero);
        $this->assertEquals(0.0, $resultZero['transactions'][0]['amount']);
        
        // Test with maximum amount (12 digits = 999999999999 haléře = 9999999999.99)
        $dataMax = "0750000000001234567000000000987654300012345678999999999999900100123456789012345678901234567890210825                    01101210825";
        $resultMax = $this->parser->parse($dataMax);
        $this->assertEquals(9999999999.99, $resultMax['transactions'][0]['amount']);
    }

    /**
     * Test parsing with non-numeric amounts
     */
    public function testNonNumericAmounts(): void
    {
        $data = "075000000000123456700000000098765430001234567890000ABCDEF00100123456789012345678901234567890210825                    01101210825";
        
        $result = $this->parser->parse($data);
        
        // Should default to 0.0 for non-numeric amounts
        $this->assertEquals(0.0, $result['transactions'][0]['amount']);
    }

    /**
     * Test parsing lines shorter than expected
     */
    public function testShortLines(): void
    {
        $shortLine = "074short";
        
        $result = $this->parser->parse($shortLine);
        
        $this->assertCount(1, $result['statements']);
        $statement = $result['statements'][0];
        
        // Should handle gracefully without crashing
        $this->assertEquals('074', $statement['record_type']);
        $this->assertEquals('short', $statement['account_number']); // Truncated
    }

    /**
     * Test parsing with only whitespace
     */
    public function testWhitespaceOnly(): void
    {
        $data = "   \n  \t  \n   \n";
        
        $result = $this->parser->parse($data);
        
        $this->assertEmpty($result['statements']);
        $this->assertEmpty($result['transactions']);
        $this->assertEmpty($result['raw_records']);
    }

    /**
     * Test parsing with mixed line endings (CRLF, LF, CR)
     */
    public function testMixedLineEndings(): void
    {
        $data = "074000000000123456701234567890123456780825000000010000000+000000009500000+000000000500000+000000000000000+001210825\r\n";
        $data .= "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825\r";
        $data .= "0750000000001234567000000001111222200012345678910000000150000200987654321098765432100000000000210825                    01101210825\n";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        $this->assertCount(2, $result['transactions']);
        $this->assertCount(3, $result['raw_records']);
    }

    /**
     * Test parsing with Unicode BOM
     */
    public function testUnicodeBOM(): void
    {
        $bom = "\xEF\xBB\xBF"; // UTF-8 BOM
        $data = $bom . "0740000000001234567Test Account Name   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        $this->assertEquals('074', $result['statements'][0]['record_type']);
    }

    /**
     * Test parsing file with read permission issues
     */
    public function testFileReadPermissions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // Try to read a directory instead of a file
        $this->parser->parseFile('/tmp');
    }

    /**
     * Test parsing with unusual record combinations
     */
    public function testUnusualRecordCombinations(): void
    {
        // Mix of valid and invalid record types
        $data = "0740000000001234567Test Account        200825000000010000000+000000009500000+000000000500000+000000000000000+001210825\n";
        $data .= "999invalid_record_type\n";
        $data .= "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825\n";
        $data .= "073another_invalid_type\n";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']); // Only 074 parsed
        $this->assertCount(1, $result['transactions']); // Only 075 parsed
        $this->assertCount(4, $result['raw_records']); // All records stored as raw
    }

    /**
     * Test format detection with mixed length records
     */
    public function testMixedLengthFormatDetection(): void
    {
        // Start with short record, then long record
        $data = "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825\n";
        $data .= "0750000000001234567000000000987654300012345678900000000100000101234567890123456789012345678902108252025-08-21                Payment for servicesAdditional payment informationFurther detailsExtra payment notesInternal referenceREF123456789098765ABCDEFGHIJKLMCZKEXTENDED RECIPIENT BANK NAME       Exchange rate information";
        
        $result = $this->parser->parse($data);
        
        // Should detect extended format based on the longer record
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $result['format_version']);
    }

    /**
     * Test parsing with trailing spaces and tabs
     */
    public function testTrailingWhitespace(): void
    {
        $data = "0740000000001234567Test Account Name   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825   \t   \n";
        $data .= "0750000000001234567000000000987654300012345678900000000050000100123456789012345678901234567890210825                    01101210825\t\t  ";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        $this->assertCount(1, $result['transactions']);
        
        // Data should be trimmed
        $this->assertEquals('Test Account Name', $result['statements'][0]['account_name']);
    }

    /**
     * Test parsing very large files (stress test)
     */
    public function testLargeFileHandling(): void
    {
        $data = "";
        // Create a large file with 1000 records
        for ($i = 0; $i < 1000; $i++) {
            $data .= sprintf("0750000000001234567000000000987654300012345678900000000%06d100123456789012345678901234567890210825                    01101210825\n", $i);
        }
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1000, $result['transactions']);
        $this->assertCount(1000, $result['raw_records']);
        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        
        // Verify amounts are parsed correctly
        for ($i = 0; $i < 10; $i++) { // Check first 10
            $this->assertEquals((float)$i, $result['transactions'][$i]['amount']);
        }
    }

    /**
     * Test parsing with invalid UTF-8 sequences
     */
    public function testInvalidUtf8Sequences(): void
    {
        // Invalid UTF-8 byte sequence
        $data = "074000000000123456789Test \xFF\xFE Account \x80\x81   200825000000010000000+000000009500000+000000000500000+000000000000000+001210825";
        
        $result = $this->parser->parse($data);
        
        $this->assertCount(1, $result['statements']);
        // Should handle gracefully without crashing
        $this->assertEquals('074', $result['statements'][0]['record_type']);
    }
}
