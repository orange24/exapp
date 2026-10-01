<?php

namespace Tests\Unit\Sanction;

use App\Services\Sanction\IdentifierNormalizer;
use PHPUnit\Framework\TestCase;

class IdentifierNormalizerTest extends TestCase
{
    public function test_splits_multiple_passports_separated_by_comma(): void
    {
        $this->assertSame(
            ['Jordan 654781', 'Jordan 286062'],
            IdentifierNormalizer::split('Jordan 654781 , Jordan 286062')
        );
    }

    public function test_split_returns_empty_array_for_blank_and_na(): void
    {
        $this->assertSame([], IdentifierNormalizer::split(''));
        $this->assertSame([], IdentifierNormalizer::split('   '));
        $this->assertSame([], IdentifierNormalizer::split('na'));
        $this->assertSame([], IdentifierNormalizer::split('NA'));
        $this->assertSame([], IdentifierNormalizer::split('-'));
    }

    public function test_normalize_strips_country_prefix_and_keeps_number(): void
    {
        $this->assertSame('654781', IdentifierNormalizer::normalize('Jordan 654781'));
    }

    public function test_normalize_keeps_alphanumeric_passport_intact(): void
    {
        $this->assertSame('OT0537103', IdentifierNormalizer::normalize('OT0537103'));
    }

    public function test_normalize_keeps_thai_national_id(): void
    {
        $this->assertSame('5960500028101', IdentifierNormalizer::normalize('5960500028101'));
    }

    public function test_normalize_strips_separators_and_uppercases(): void
    {
        $this->assertSame('AB123456', IdentifierNormalizer::normalize('ab-123 456'));
        $this->assertSame('5960500028101', IdentifierNormalizer::normalize('5-9605-00028-10-1'));
    }

    public function test_normalize_falls_back_to_alphanumeric_when_no_digits(): void
    {
        $this->assertSame('UNKNOWN', IdentifierNormalizer::normalize('unknown'));
    }

    public function test_issuing_country_returns_alphabetic_only_token(): void
    {
        $this->assertSame('Jordan', IdentifierNormalizer::issuingCountry('Jordan 654781'));
    }

    public function test_issuing_country_is_null_when_token_contains_digits(): void
    {
        $this->assertNull(IdentifierNormalizer::issuingCountry('OT0537103'));
        $this->assertNull(IdentifierNormalizer::issuingCountry('5960500028101'));
    }

    public function test_is_thai_national_id_requires_exactly_13_digits(): void
    {
        $this->assertTrue(IdentifierNormalizer::isThaiNationalId('5960500028101'));
        $this->assertFalse(IdentifierNormalizer::isThaiNationalId('596050002810'));
        $this->assertFalse(IdentifierNormalizer::isThaiNationalId('OT0537103'));
    }
}
