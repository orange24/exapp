<?php

namespace Tests\Unit\Sanction;

use App\Services\Sanction\MatchScorer;
use PHPUnit\Framework\TestCase;

class MatchScorerTest extends TestCase
{
    // --- base score ---------------------------------------------------

    public function test_identical_normalized_names_score_95(): void
    {
        $this->assertSame(
            ['type' => 'name_exact', 'score' => 95.0],
            MatchScorer::nameScore('AMRAN MING', 'AMRAN MING')
        );
    }

    public function test_swapped_name_order_still_scores_as_exact(): void
    {
        $this->assertSame(
            ['type' => 'name_exact', 'score' => 95.0],
            MatchScorer::nameScore('MING AMRAN', 'AMRAN MING')
        );
    }

    public function test_entry_name_fully_contained_in_customer_name_scores_85(): void
    {
        $result = MatchScorer::nameScore('AMRAN BIN MING', 'AMRAN MING');

        $this->assertSame('token_containment', $result['type']);
        $this->assertSame(85.0, $result['score']);
    }

    public function test_one_character_typo_scores_in_fuzzy_band(): void
    {
        $result = MatchScorer::nameScore('AMRAN MINGG', 'AMRAN MING');

        $this->assertSame('name_fuzzy', $result['type']);
        $this->assertGreaterThanOrEqual(60.0, $result['score']);
        $this->assertLessThanOrEqual(80.0, $result['score']);
    }

    public function test_completely_different_names_do_not_match(): void
    {
        $this->assertNull(MatchScorer::nameScore('SOMCHAI JAIDEE', 'AMRAN MING'));
    }

    public function test_empty_input_does_not_match(): void
    {
        $this->assertNull(MatchScorer::nameScore('', 'AMRAN MING'));
        $this->assertNull(MatchScorer::nameScore('AMRAN MING', ''));
    }

    // --- nationality ---------------------------------------------------

    public function test_nationality_compare_handles_iso2_vs_iso3(): void
    {
        $this->assertSame('match', MatchScorer::compareNationality('THA', 'TH'));
        $this->assertSame('match', MatchScorer::compareNationality('TH', 'THA'));
        $this->assertSame('match', MatchScorer::compareNationality('JOR', 'Jordan'));
    }

    public function test_nationality_compare_detects_mismatch(): void
    {
        $this->assertSame('mismatch', MatchScorer::compareNationality('JPN', 'TH'));
    }

    public function test_nationality_compare_is_unknown_when_either_side_missing(): void
    {
        $this->assertSame('unknown', MatchScorer::compareNationality(null, 'TH'));
        $this->assertSame('unknown', MatchScorer::compareNationality('TH', null));
        $this->assertSame('unknown', MatchScorer::compareNationality('TH', ''));
    }

    // --- date of birth --------------------------------------------------

    public function test_dob_compare_matches_across_source_formats(): void
    {
        $this->assertSame('match', MatchScorer::compareDob('1981-12-18', '18-12-1981'));
    }

    public function test_dob_compare_matches_on_year_only_when_entry_has_year_only(): void
    {
        $this->assertSame('match', MatchScorer::compareDob('1981-12-18', '1981'));
    }

    public function test_dob_compare_detects_mismatch(): void
    {
        $this->assertSame('mismatch', MatchScorer::compareDob('1995-01-01', '18-12-1981'));
    }

    public function test_dob_compare_is_unknown_when_either_side_missing(): void
    {
        $this->assertSame('unknown', MatchScorer::compareDob(null, '18-12-1981'));
        $this->assertSame('unknown', MatchScorer::compareDob('1981-12-18', null));
        $this->assertSame('unknown', MatchScorer::compareDob('1981-12-18', 'na'));
    }

    // --- modifiers -------------------------------------------------------

    public function test_matching_nationality_raises_score(): void
    {
        $this->assertSame(93.5, MatchScorer::applyModifiers(85.0, 'match', 'unknown'));
    }

    public function test_mismatching_nationality_lowers_score(): void
    {
        $this->assertSame(59.5, MatchScorer::applyModifiers(85.0, 'mismatch', 'unknown'));
    }

    public function test_mismatching_dob_halves_score(): void
    {
        $this->assertSame(42.5, MatchScorer::applyModifiers(85.0, 'unknown', 'mismatch'));
    }

    public function test_score_is_capped_at_99_so_it_never_collides_with_exact_id(): void
    {
        $this->assertSame(99.0, MatchScorer::applyModifiers(95.0, 'match', 'match'));
    }

    public function test_unknown_on_both_sides_leaves_score_untouched(): void
    {
        $this->assertSame(85.0, MatchScorer::applyModifiers(85.0, 'unknown', 'unknown'));
    }
}
