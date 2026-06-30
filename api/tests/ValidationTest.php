<?php

use TradersApi\Validation;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testRequireFieldsWithAllPresent(): void
    {
        $data = [
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT'
        ];

        $result = Validation::requireFields($data, ['symbol', 'side', 'type']);

        $this->assertNull($result);
    }

    public function testRequireFieldsWithMissing(): void
    {
        $data = [
            'symbol' => 'BTCUSDT'
        ];

        $result = Validation::requireFields($data, ['symbol', 'side', 'type']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('side', $result);
    }

    public function testRequireFieldsWithEmptyValue(): void
    {
        $data = [
            'symbol' => '',
            'side' => 'BUY'
        ];

        $result = Validation::requireFields($data, ['symbol', 'side']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('symbol', $result);
    }

    public function testRequireFieldsWithNullValue(): void
    {
        $data = [
            'symbol' => null,
            'side' => 'BUY'
        ];

        $result = Validation::requireFields($data, ['symbol', 'side']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('symbol', $result);
    }

    public function testRequireFieldsWithZeroValue(): void
    {
        $data = [
            'quantity' => 0,
            'side' => 'BUY'
        ];

        $result = Validation::requireFields($data, ['quantity', 'side']);

        // Zero is considered "empty" in PHP
        $this->assertNotNull($result);
        $this->assertStringContainsString('quantity', $result);
    }

    public function testRequireFieldsWithEmptyFieldsArray(): void
    {
        $data = [
            'symbol' => 'BTCUSDT'
        ];

        $result = Validation::requireFields($data, []);

        $this->assertNull($result);
    }

    public function testRequireFieldsWithEmptyData(): void
    {
        $result = Validation::requireFields([], ['symbol']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('symbol', $result);
    }

    public function testRequireFieldsReturnsPtMessage(): void
    {
        $data = ['symbol' => 'BTCUSDT'];

        $result = Validation::requireFields($data, ['price']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('obrigatório', $result);
    }

    public function testRequireFieldsFirstMissingReturnsFirst(): void
    {
        $data = [];

        $result = Validation::requireFields($data, ['first_field', 'second_field', 'third_field']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('first_field', $result);
        // Should only return the first missing
        $this->assertStringNotContainsString('second_field', $result);
    }

    public function testRequireFieldsWithNestedArray(): void
    {
        $data = [
            'symbol' => 'BTCUSDT',
            'filters' => ['minPrice' => '0.01']  // Not empty
        ];

        $result = Validation::requireFields($data, ['symbol', 'filters']);

        $this->assertNull($result);
    }

    public function testRequireFieldsWithBooleanFalse(): void
    {
        $data = [
            'active' => false,
            'symbol' => 'BTCUSDT'
        ];

        // Boolean false is considered "empty"
        $result = Validation::requireFields($data, ['active']);

        $this->assertNotNull($result);
    }

    public function testRequireFieldsWithString(): void
    {
        $data = [
            'symbol' => 'BTCUSDT',
            'comment' => 'Test order'
        ];

        $result = Validation::requireFields($data, ['symbol', 'comment']);

        $this->assertNull($result);
    }

    public function testRequireFieldsWithWhitespaceOnly(): void
    {
        $data = [
            'symbol' => '   '  // Only whitespace - NOT trimmed, so not "empty"
        ];

        // PHP's empty() considers "   " as non-empty
        $result = Validation::requireFields($data, ['symbol']);

        $this->assertNull($result);
    }
}
