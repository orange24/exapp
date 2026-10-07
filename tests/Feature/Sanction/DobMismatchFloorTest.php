<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionScreeningMatch;
use App\Services\Sanction\MatchScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * เกณฑ์แดง/เตือนเก็บอยู่ในตาราง settings จึงต้องมีฐานข้อมูล
 * เทสชุดนี้อยู่ชั้น Feature ไม่ใช่ Unit
 */
class DobMismatchFloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mistyped_birth_date_cannot_silence_an_exact_name_match(): void
    {
        // วันเกิดเป็นข้อมูลที่พนักงานพิมพ์เอง ไม่ได้ดึงจากชิปบัตร
        // พิมพ์ผิดหลักเดียวต้องไม่ทำให้ชื่อที่ตรงเป๊ะหายไปทั้งแถบ
        $score = MatchScorer::applyModifiers(
            MatchScorer::SCORE_NAME_EXACT, 'unknown', 'mismatch', 1.0,
            SanctionScreeningMatch::TYPE_NAME_EXACT,
        );

        $this->assertSame('potential_match', MatchScorer::classify($score));
        $this->assertSame('orange', MatchScorer::severity($score));
    }

    public function test_a_mistyped_birth_date_cannot_silence_a_full_token_containment_match(): void
    {
        $score = MatchScorer::applyModifiers(
            MatchScorer::SCORE_TOKEN_CONTAINMENT, 'unknown', 'mismatch', 1.0,
            SanctionScreeningMatch::TYPE_TOKEN_CONTAINMENT,
        );

        $this->assertSame('orange', MatchScorer::severity($score));
    }

    public function test_a_birth_date_mismatch_still_clears_a_weak_name_match(): void
    {
        // ชื่อชิ้นเดียวหรือชื่อคล้ายไม่ใช่การระบุตัวบุคคล ถ้าวันเกิดไม่ตรงก็ตัดได้
        // ไม่งั้นพื้นที่ตั้งไว้จะกลายเป็นแหล่งผลิต false positive ชุดใหม่
        $score = MatchScorer::applyModifiers(
            MatchScorer::SCORE_SINGLE_TOKEN, 'unknown', 'mismatch', 0.55,
            SanctionScreeningMatch::TYPE_NAME_FUZZY,
        );

        $this->assertSame('clear', MatchScorer::classify($score));
    }

    public function test_the_floor_does_not_lift_a_birth_date_that_actually_matches(): void
    {
        // พื้นมีไว้กันเคสพิมพ์ผิด ไม่ใช่มาแทนคะแนนจริง วันเกิดตรงต้องยังได้แดง
        $score = MatchScorer::applyModifiers(
            MatchScorer::SCORE_NAME_EXACT, 'unknown', 'match', 0.55,
            SanctionScreeningMatch::TYPE_NAME_EXACT,
        );

        $this->assertSame('red', MatchScorer::severity($score));
    }
}
