<?php

namespace Tests\Unit\Sanction;

use App\Services\Sanction\MatchScorer;
use PHPUnit\Framework\TestCase;

/**
 * วัดจากรายชื่อจริงของ ปปง. บน production: คำว่า AL ปรากฏใน 157 ชื่อ,
 * MOHAMMAD 114, ABDUL 98 — ขณะที่ 51% ของคำทั้งหมดปรากฏครั้งเดียว
 *
 * การตรงกันที่คำพบบ่อยจึงไม่ใช่หลักฐานระดับเดียวกับการตรงกันที่คำหายาก
 * ลูกค้าตะวันออกกลางแทบทุกคนมีคำว่า MOHAMED/AHMED อยู่ในชื่อ
 */
class NameRarityTest extends TestCase
{
    public function test_a_very_common_name_is_heavily_discounted(): void
    {
        // ค่าข้อมูลต่ำ = ชื่อที่ใครก็ชื่อนี้
        $this->assertSame(0.55, MatchScorer::rarityFactor(4.3));
        $this->assertSame(0.55, MatchScorer::rarityFactor(5.0));
    }

    public function test_a_distinctive_name_is_not_discounted(): void
    {
        // AMRAN MING = 14.0, IYAD NAZMI SALIH KHALIL = 26.0
        $this->assertSame(1.0, MatchScorer::rarityFactor(12.0));
        $this->assertSame(1.0, MatchScorer::rarityFactor(26.0));
    }

    public function test_middle_range_scales_smoothly(): void
    {
        $mid = MatchScorer::rarityFactor(8.5);

        $this->assertGreaterThan(0.55, $mid);
        $this->assertLessThan(1.0, $mid);
    }

    // ───── โหมดที่ 1: ลูกค้าไม่มีวันเกิด — ชื่อเป็นตัวตัดสินอย่างเดียว ─────

    public function test_common_name_without_a_birth_date_falls_below_the_alert_threshold(): void
    {
        // MOHAMED AHMED มีค่าข้อมูล 8.4 — เคสที่หน้าเคาน์เตอร์เจอทุกวัน
        $score = MatchScorer::applyModifiers(85.0, 'unknown', 'unknown', MatchScorer::rarityFactor(8.4));

        $this->assertLessThan(70.0, $score, 'ชื่อสามัญโดยไม่มีอะไรยืนยัน ไม่ควรรบกวนพนักงาน');
    }

    public function test_distinctive_name_without_a_birth_date_still_alerts(): void
    {
        // ABU RUSDAN = 12.7 — ชื่อเจาะจง ต้องเตือนแม้ไม่มีวันเกิด
        $score = MatchScorer::applyModifiers(85.0, 'unknown', 'unknown', MatchScorer::rarityFactor(12.7));

        $this->assertGreaterThanOrEqual(85.0, $score);
    }

    // ───── โหมดที่ 2: มีวันเกิดและไม่ตรง — ตัดทิ้งไม่ว่าชื่อหายากแค่ไหน ─────

    public function test_a_mismatched_birth_date_kills_even_a_distinctive_name(): void
    {
        $score = MatchScorer::applyModifiers(95.0, 'unknown', 'mismatch', MatchScorer::rarityFactor(14.0));

        $this->assertLessThan(70.0, $score, 'วันเกิดคนละวัน = คนละคน');
    }

    // ───── โหมดที่ 3: มีวันเกิดและตรง — ความหายากไม่สำคัญอีกต่อไป ─────

    public function test_a_matching_birth_date_overrides_the_commonness_discount(): void
    {
        // ชื่อสามัญที่สุด แต่วันเกิดตรงเป๊ะ = หลักฐานแรงมาก
        // ถ้าปล่อยให้ตัวคูณความหายากกดอยู่ จะกลายเป็นกดเคสที่ควรแดงที่สุด
        $score = MatchScorer::applyModifiers(85.0, 'unknown', 'match', MatchScorer::rarityFactor(4.3));

        $this->assertGreaterThanOrEqual(85.0, $score, 'ชื่อสามัญ + วันเกิดตรง ต้องขึ้นแดง');
    }

    public function test_rarity_defaults_to_no_discount_when_not_supplied(): void
    {
        // ผู้เรียกเดิมที่ยังไม่ส่งค่าความหายากมา ต้องได้พฤติกรรมเดิม
        $this->assertSame(
            MatchScorer::applyModifiers(85.0, 'match', 'unknown'),
            MatchScorer::applyModifiers(85.0, 'match', 'unknown', 1.0)
        );
    }
}
