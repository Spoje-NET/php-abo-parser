<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SpojeNet\AboParser\AboParser;

/**
 * Integration tests for ABO file parsing
 */
class AboFileParsingTest extends TestCase
{
    private AboParser $parser;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->parser = new AboParser();
        $this->fixturesPath = __DIR__ . '/../Fixtures/';
    }

    /**
     * Test parsing basic format file
     */
    public function testParseBasicFormatFile(): void
    {
        $filePath = $this->fixturesPath . 'basic_format.abo';
        $result = $this->parser->parseFile($filePath);

        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        $this->assertCount(1, $result['statements']);
        $this->assertCount(2, $result['transactions']);
        
        // Verify statement data
        $statement = $result['statements'][0];
        $this->assertEquals('074', $statement['record_type']);
        $this->assertEquals('Test Account Name', $statement['account_name']);
        $this->assertEquals(100000.0, $statement['old_balance']);
        $this->assertEquals(95000.0, $statement['new_balance']);
        
        // Verify transaction data
        $transaction1 = $result['transactions'][0];
        $this->assertEquals('075', $transaction1['record_type']);
        $this->assertEquals(AboParser::FORMAT_BASIC, $transaction1['format']);
        $this->assertEquals(500.0, $transaction1['amount']);
        
        $transaction2 = $result['transactions'][1];
        $this->assertEquals(1500.0, $transaction2['amount']);
    }

    /**
     * Test parsing extended format file
     */
    public function testParseExtendedFormatFile(): void
    {
        $filePath = $this->fixturesPath . 'extended_format.abo';
        $result = $this->parser->parseFile($filePath);

        $this->assertEquals(AboParser::FORMAT_EXTENDED, $result['format_version']);
        $this->assertCount(1, $result['statements']);
        $this->assertCount(1, $result['transactions']);
        
        // Verify statement data
        $statement = $result['statements'][0];
        $this->assertEquals('074', $statement['record_type']);
        $this->assertEquals('Extended Test Account', $statement['account_name']);
        
        // Verify extended transaction data
        $transaction = $result['transactions'][0];
        $this->assertEquals('075', $transaction['record_type']);
        $this->assertEquals(AboParser::FORMAT_EXTENDED, $transaction['format']);
        $this->assertEquals(1000.0, $transaction['amount']);
        
        // Verify extended fields are present
        $this->assertArrayHasKey('message_for_recipient', $transaction);
        $this->assertArrayHasKey('message_for_recipient_2', $transaction);
        $this->assertArrayHasKey('message_for_sender', $transaction);
        $this->assertArrayHasKey('identification_reference', $transaction);
    }

    /**
     * Test parsing empty file
     */
    public function testParseEmptyFile(): void
    {
        $filePath = $this->fixturesPath . 'empty.abo';
        $result = $this->parser->parseFile($filePath);

        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']); // Default format
        $this->assertEmpty($result['statements']);
        $this->assertEmpty($result['transactions']);
        $this->assertEmpty($result['raw_records']);
    }

    /**
     * Test parsing malformed file
     */
    public function testParseMalformedFile(): void
    {
        $filePath = $this->fixturesPath . 'malformed.abo';
        $result = $this->parser->parseFile($filePath);

        // Should not crash, but should store raw records
        $this->assertIsArray($result);
        $this->assertArrayHasKey('raw_records', $result);
        $this->assertNotEmpty($result['raw_records']);
        
        // Should have captured all lines as raw records
        $this->assertCount(4, $result['raw_records']);
    }

    /**
     * Test parsing real-world sample file
     */
    public function testParseRealWorldSample(): void
    {
        $samplePath = __DIR__ . '/../../tests/27_CZ8408000000000002122722_06029396af87517e_CZK_2025-08-21.abo-standard';
        
        if (!file_exists($samplePath)) {
            $this->markTestSkipped('Real-world sample file not available');
        }

        $result = $this->parser->parseFile($samplePath);

        $this->assertEquals(AboParser::FORMAT_BASIC, $result['format_version']);
        $this->assertNotEmpty($result['statements']);
        $this->assertNotEmpty($result['transactions']);
        
        // Verify data integrity
        foreach ($result['statements'] as $statement) {
            $this->assertArrayHasKey('record_type', $statement);
            $this->assertArrayHasKey('account_number', $statement);
            $this->assertArrayHasKey('raw_line', $statement);
        }
        
        foreach ($result['transactions'] as $transaction) {
            $this->assertArrayHasKey('record_type', $transaction);
            $this->assertArrayHasKey('format', $transaction);
            $this->assertArrayHasKey('amount', $transaction);
            $this->assertArrayHasKey('raw_line', $transaction);
        }
    }

    /**
     * Test character encoding handling
     */
    public function testCharacterEncodingHandling(): void
    {
        // Test with Czech characters (if available in fixtures)
        $filePath = $this->fixturesPath . 'basic_format.abo';
        $result = $this->parser->parseFile($filePath);
        
        // Should not crash with encoding issues
        $this->assertIsArray($result);
        $this->assertArrayHasKey('format_version', $result);
    }

    /**
     * Test file with mixed record types
     */
    public function testMixedRecordTypes(): void
    {
        $filePath = $this->fixturesPath . 'basic_format.abo';
        $result = $this->parser->parseFile($filePath);
        
        // Should parse both 074 and 075 records
        $this->assertNotEmpty($result['statements']); // 074 records
        $this->assertNotEmpty($result['transactions']); // 075 records
        $this->assertEquals(
            count($result['statements']) + count($result['transactions']),
            count($result['raw_records'])
        );
    }

    /**
     * Test format consistency across multiple transactions
     */
    public function testFormatConsistency(): void
    {
        $filePath = $this->fixturesPath . 'basic_format.abo';
        $result = $this->parser->parseFile($filePath);
        
        // All transactions should have the same format as detected
        foreach ($result['transactions'] as $transaction) {
            $this->assertEquals($result['format_version'], $transaction['format']);
        }
    }
}
