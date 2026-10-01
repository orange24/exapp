<?php

namespace Tests\Feature\Sanction;

use App\Services\Sanction\MatchScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * แยกจาก MatchScorerTest เพราะ classify()/severity() อ่านเกณฑ์จากตาราง settings
 * ซึ่งต้องมี Laravel app + DB
 */
class MatchScorerThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_classify_returns_confirmed_only_at_100(): void
    {
        $this->assertSame('confirmed_match', MatchScorer::classify(100.0));
        $this->assertSame('potential_match', MatchScorer::classify(99.0));
    }

    public function test_classify_potential_match_band(): void
    {
        $this->assertSame('potential_match', MatchScorer::classify(85.0));
        $this->assertSame('potential_match', MatchScorer::classify(70.0));
    }

    public function test_classify_clear_below_threshold(): void
    {
        $this->assertSame('clear', MatchScorer::classify(69.9));
        $this->assertSame('clear', MatchScorer::classify(0.0));
    }

    public function test_severity_separates_red_from_orange(): void
    {
        $this->assertSame('red', MatchScorer::severity(90.0));
        $this->assertSame('red', MatchScorer::severity(85.0));
        $this->assertSame('orange', MatchScorer::severity(84.9));
        $this->assertSame('orange', MatchScorer::severity(70.0));
        $this->assertSame('none', MatchScorer::severity(69.9));
    }

    public function test_thresholds_can_be_tuned_without_a_deploy(): void
    {
        \App\Models\Setting::set('sanction_threshold_potential', '75');

        $this->assertSame('clear', MatchScorer::classify(72.0));
        $this->assertSame('potential_match', MatchScorer::classify(76.0));
    }
}
