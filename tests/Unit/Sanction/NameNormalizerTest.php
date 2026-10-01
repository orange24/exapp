<?php

namespace Tests\Unit\Sanction;

use App\Services\Sanction\NameNormalizer;
use PHPUnit\Framework\TestCase;

class NameNormalizerTest extends TestCase
{
    public function test_normalize_uppercases_and_collapses_whitespace(): void
    {
        $this->assertSame('AMRAN MING', NameNormalizer::normalize('  amran   ming '));
    }

    public function test_normalize_sorts_tokens_so_swapped_order_matches(): void
    {
        $this->assertSame(
            NameNormalizer::normalize('AMRAN MING'),
            NameNormalizer::normalize('MING AMRAN')
        );
    }

    public function test_normalize_strips_latin_titles(): void
    {
        $this->assertSame('AMRAN MING', NameNormalizer::normalize('MR. AMRAN MING'));
        $this->assertSame('AMRAN MING', NameNormalizer::normalize('Mrs Amran Ming'));
        $this->assertSame('AMRAN MING', NameNormalizer::normalize('MS AMRAN MING'));
    }

    public function test_normalize_strips_thai_titles(): void
    {
        $this->assertSame('มิง อำรัน', NameNormalizer::normalize('นาย อำรัน มิง'));
        $this->assertSame('มิง อำรัน', NameNormalizer::normalize('นางสาว อำรัน มิง'));
    }

    public function test_normalize_strips_punctuation(): void
    {
        $this->assertSame('AMRAN MING', NameNormalizer::normalize('AMRAN, MING.'));
    }

    public function test_normalize_returns_empty_string_for_blank_markers(): void
    {
        $this->assertSame('', NameNormalizer::normalize(''));
        $this->assertSame('', NameNormalizer::normalize('na'));
        $this->assertSame('', NameNormalizer::normalize('   '));
    }

    public function test_split_numbered_parts_breaks_un_style_name(): void
    {
        $this->assertSame(
            ['IYAD', 'NAZMI', 'SALIH', 'KHALIL'],
            NameNormalizer::splitNumberedParts('1. IYAD 2. NAZMI 3. SALIH 4. KHALIL')
        );
    }

    public function test_split_numbered_parts_strips_single_leading_number(): void
    {
        $this->assertSame(['อำรัน มิง'], NameNormalizer::splitNumberedParts('1. อำรัน มิง'));
    }

    public function test_split_numbered_parts_passes_through_plain_name(): void
    {
        $this->assertSame(['AMRAN MING'], NameNormalizer::splitNumberedParts('AMRAN MING'));
    }

    public function test_detect_script_identifies_thai_and_latin(): void
    {
        $this->assertSame('th', NameNormalizer::detectScript('อำรัน มิง'));
        $this->assertSame('latin', NameNormalizer::detectScript('AMRAN MING'));
    }

    public function test_soundex_of_is_empty_for_thai(): void
    {
        $this->assertSame('', NameNormalizer::soundexOf('อำรัน มิง'));
    }

    public function test_soundex_of_is_stable_for_similar_latin_spellings(): void
    {
        $this->assertSame(
            NameNormalizer::soundexOf(NameNormalizer::normalize('AMRAN MING')),
            NameNormalizer::soundexOf(NameNormalizer::normalize('AMRAN MINGG'))
        );
    }

    public function test_tokens_returns_sorted_unique_tokens(): void
    {
        $this->assertSame(['AMRAN', 'MING'], NameNormalizer::tokens('MING AMRAN MING'));
    }
}
