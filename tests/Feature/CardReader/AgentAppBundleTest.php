<?php

namespace Tests\Feature\CardReader;

use Tests\TestCase;

/**
 * โครงสร้างของ .app เป็นสิ่งเดียวที่กั้นระหว่าง "ดับเบิลคลิกแล้วใช้ได้"
 * กับข้อความ "แอปไม่ตอบสนอง" ที่พนักงานสาขาแก้เองไม่ได้
 */
class AgentAppBundleTest extends TestCase
{
    private function script(): string
    {
        $path = base_path('agent/scripts/make-app-bundle.sh');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_the_bundle_executable_is_a_launcher_that_exits(): void
    {
        $source = $this->script();

        /*
         * ดับเบิลคลิก .app ที่กำลังรันอยู่ macOS จะพยายามปลุก process เดิมให้มา
         * ข้างหน้าแทนที่จะเริ่มตัวใหม่ โปรแกรม Go ไม่มี event loop ของ Cocoa
         * จึงไม่ตอบ แล้ว Finder ขึ้นว่า "ไม่ตอบสนอง"
         *
         * ตัวที่ macOS เห็นต้องจบทันที เพื่อให้มันไม่เคยคิดว่าแอปนี้กำลังรัน
         */
        $this->assertStringContainsString('exapp-card-agent-bin', $source, 'โปรแกรมจริงต้องแยกออกจากตัวที่ macOS เรียก');
        $this->assertMatchesRegularExpression(
            '/exapp-card-agent-bin.*&\s*\n\s*exit 0/',
            $source,
            'ตัวที่ macOS เรียกต้องปล่อยงานแล้วจบทันที',
        );
    }

    public function test_the_real_binary_lives_outside_the_macos_folder(): void
    {
        $source = $this->script();

        // ถ้าโปรแกรมจริงอยู่ใน Contents/MacOS มันจะกลายเป็นตัวที่ macOS เฝ้า
        // แล้วปัญหาเดิมกลับมาทันที
        $this->assertStringContainsString('Contents/Resources/exapp-card-agent-bin', $source);
    }

    public function test_the_app_stays_out_of_the_dock(): void
    {
        // โปรแกรมทำงานเบื้องหลัง หน้าตาอยู่ในเบราว์เซอร์ ไอคอนใน Dock
        // ที่กดแล้วไม่มีหน้าต่างขึ้นมีแต่ทำให้สับสน
        $this->assertStringContainsString('LSUIElement', $this->script());
    }
}
