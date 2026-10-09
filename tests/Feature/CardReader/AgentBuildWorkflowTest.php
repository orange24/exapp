<?php

namespace Tests\Feature\CardReader;

use Tests\TestCase;

/**
 * ไฟล์ .exe สำหรับสาขามาจาก GitHub Actions เท่านั้น เพราะ cgo ข้าม platform ไม่ได้
 * และไม่มีเครื่อง Windows ในมือ workflow พังเมื่อไหร่ก็แจกโปรแกรมไม่ได้เมื่อนั้น
 */
class AgentBuildWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $path = base_path('.github/workflows/card-agent.yml');

        $this->assertFileExists($path, 'ไม่มี workflow build agent');

        return (string) file_get_contents($path);
    }

    public function test_the_workflow_takes_its_go_version_from_go_mod(): void
    {
        $source = $this->workflow();

        /*
         * เคยตรึงไว้ที่ '1.22' แล้ว go.mod ขยับเป็น 1.26 ตอนเพิ่มไลบรารีอ่านพาสปอร์ต
         * workflow ยังติดตั้ง 1.22 อยู่ — build พังโดยไม่มีใครนึกถึง เพราะมันอยู่
         * คนละไฟล์กับสิ่งที่เปลี่ยน
         */
        $this->assertStringContainsString('go-version-file: agent/go.mod', $source);
        $this->assertStringNotContainsString("go-version: '", $source, 'อย่าตรึงรุ่น Go ไว้ตายตัว');
    }

    public function test_windows_is_built_on_windows(): void
    {
        // cgo ข้าม platform ไม่ได้ — ตัวอ่านบัตรเรียก winscard.dll ของ Windows เอง
        $this->assertStringContainsString('runs-on: windows-latest', $this->workflow());
    }

    public function test_the_built_binary_is_run_before_it_is_published(): void
    {
        // คอมไพล์ผ่านไม่ได้แปลว่ารันได้ ถ้าแจกไฟล์ที่เปิดไม่ขึ้นไป 20 สาขา
        // กว่าจะรู้ก็ตอนที่ทุกสาขาพยายามติดตั้งพร้อมกัน
        $this->assertStringContainsString('exapp-card-agent.exe -version', $this->workflow());
    }

    public function test_a_release_is_only_cut_from_a_tag(): void
    {
        $source = $this->workflow();

        $this->assertStringContainsString("if: github.ref_type == 'tag'", $source);
        $this->assertStringContainsString("tags: ['card-agent-v*']", $source);
    }
}
