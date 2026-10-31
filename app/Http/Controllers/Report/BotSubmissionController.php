<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Bot\BotSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * ส่งข้อมูลให้แบงค์ชาติ — ใส่รายการซื้อ/ขายรายเดือนลงไฟล์ template ทางการ
 *
 * ต่างจาก BotMonthlyReportController ที่สร้างไฟล์ Excel ขึ้นเองทั้งไฟล์ —
 * หน้านี้ใช้ไฟล์ template ของแบงค์ชาติจริง คงสูตร dropdown และโครงสร้างไว้
 */
class BotSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeMenu($request);

        $branches = $this->branches($request);
        [$year, $month, $group, $branch] = $this->filters($request, $branches);

        $submission = $branch ? new BotSubmission($branch, $year, $month) : null;

        return view('reports.bot-submission', [
            'branches' => $branches,
            'branch' => $branch,
            'year' => $year,
            'month' => $month,
            'group' => $group,
            'submission' => $submission,
            'rows' => $submission?->rows($group) ?? ['buy' => [], 'sell' => []],
            'counts' => $submission ? [
                BotSubmission::THAI => $this->count($submission, BotSubmission::THAI),
                BotSubmission::FOREIGN => $this->count($submission, BotSubmission::FOREIGN),
            ] : [],
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeMenu($request);

        $request->validate([
            'branch_id' => 'required|integer',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'group' => 'required|in:' . BotSubmission::THAI . ',' . BotSubmission::FOREIGN,
        ]);

        $branches = $this->branches($request);
        [$year, $month, $group, $branch] = $this->filters($request, $branches);
        abort_unless($branch && $branch->id === (int) $request->input('branch_id'), 403, 'ไม่มีสิทธิ์ออกรายงานของสาขานี้');

        $workDir = storage_path('app/bot-submission/' . uniqid('', true));
        File::ensureDirectoryExists($workDir);

        $file = (new BotSubmission($branch, $year, $month))->export($group, $workDir);

        // ลบทั้งโฟลเดอร์หลังส่งไฟล์เสร็จ ไม่ให้ไฟล์ข้อมูลลูกค้าค้างบนเซิร์ฟเวอร์
        app()->terminating(fn () => File::deleteDirectory($workDir));

        return response()->download($file['path'], $file['name']);
    }

    /**
     * สาขาที่เลือกได้ — เฉพาะสาขาของบริษัทที่กำลังทำงานอยู่ ตามขอบเขตของผู้ใช้
     */
    private function branches(Request $request)
    {
        return Branch::with('company')
            ->whereIn('id', $request->user()->getVisibleBranchIds())
            ->orderBy('branch_code')
            ->get();
    }

    /**
     * @return array{0: int, 1: int, 2: string, 3: ?Branch}
     */
    private function filters(Request $request, $branches): array
    {
        // ค่าตั้งต้นเป็นเดือนที่แล้ว — ส่งแบงค์ชาติหลังปิดเดือน
        $previous = now()->subMonthNoOverflow();
        $year = (int) $request->input('year', $previous->year);
        $month = (int) $request->input('month', $previous->month);
        $group = $request->input('group') === BotSubmission::FOREIGN ? BotSubmission::FOREIGN : BotSubmission::THAI;

        $branch = $branches->firstWhere('id', (int) $request->input('branch_id')) ?? $branches->first();

        return [$year, $month, $group, $branch];
    }

    private function count(BotSubmission $submission, string $group): array
    {
        $rows = $submission->rows($group);

        return ['buy' => count($rows['buy']), 'sell' => count($rows['sell'])];
    }

    private function authorizeMenu(Request $request): void
    {
        abort_unless($request->user()->hasMenuAccess('reports.bot-submission'), 403, 'ไม่มีสิทธิ์เข้าหน้านี้');
    }
}
