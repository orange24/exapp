<?php

namespace App\Livewire\CardReader;

use App\Models\Branch;
use App\Models\CardReaderDevice;
use App\Models\Counter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class DeviceManager extends Component
{
    public string $name = '';
    public string $counterId = '';

    /**
     * token ตัวจริงของเครื่องที่เพิ่งสร้าง
     *
     * แสดงครั้งเดียวแล้วหายไปตลอดกาล เพราะฐานข้อมูลเก็บแค่ hash
     * ถ้าทำหาย ต้องสร้างเครื่องใหม่ ไม่มีทางกู้
     */
    public string $issuedToken = '';
    public string $issuedFor = '';

    public function mount(): void
    {
        $this->counterId = $this->defaultCounterId();
    }

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|max:120',
            'counterId' => 'required|exists:counters,id',
        ], attributes: ['name' => 'ชื่อเครื่อง', 'counterId' => 'เคาน์เตอร์']);

        // รายการใน dropdown ไม่ใช่การควบคุมสิทธิ์ — ค่าที่ส่งมาแก้ได้จากฝั่งผู้ใช้
        if (! $this->countersInScope()->where('counters.id', (int) $this->counterId)->exists()) {
            $this->addError('counterId', 'เลือกได้เฉพาะเคาน์เตอร์ในสาขาของคุณ');

            return;
        }

        $token = 'crd_' . Str::random(40);

        $device = CardReaderDevice::create([
            'name' => $this->name,
            'counter_id' => (int) $this->counterId,
            'token_hash' => CardReaderDevice::hashToken($token),
            'created_by' => Auth::id(),
        ]);

        $this->issuedToken = $token;
        $this->issuedFor = $device->name;
        $this->name = '';
        $this->counterId = $this->defaultCounterId();
    }

    public function revoke(int $id): void
    {
        $query = CardReaderDevice::whereNull('revoked_at')->where('id', $id)
            ->whereHas('counter', fn ($q) => $q->whereIn('branch_id', $this->branchIdsInScope()));

        if ($query->update(['revoked_at' => now()]) === 0) {
            session()->flash('error', 'เพิกถอนไม่ได้ — ไม่พบเครื่องนี้ในสาขาของคุณ');

            return;
        }

        session()->flash('success', 'เพิกถอนเครื่องแล้ว — เครื่องนั้นส่งข้อมูลเข้าระบบไม่ได้อีก');
    }

    public function dismissToken(): void
    {
        $this->issuedToken = '';
        $this->issuedFor = '';
    }

    public function getDevicesProperty()
    {
        /*
         * เครื่องที่เพิกถอนแล้วไม่ต้องแสดง
         *
         * แถวยังอยู่ในฐานข้อมูล เพราะ revoked_at คือสิ่งที่ทำให้ token ใบนั้น
         * ใช้ไม่ได้ และเป็นหลักฐานว่าเคยมีเครื่องนี้อยู่ที่เคาน์เตอร์ไหน
         * แต่ในรายการที่คนดูทุกวัน มันเป็นแค่สิ่งรบกวนสายตา
         *
         * ขอบเขตกรองด้วย "สาขาของเครื่อง" ไม่ใช่ด้วยรายการใน dropdown เพราะ
         * เคาน์เตอร์ที่เลิกใช้ถูกปิด (is_active=false) ไม่ได้ลบ ถ้ากรองด้วย
         * เคาน์เตอร์ที่ยังเปิดอยู่ เครื่องที่ค้างอยู่ที่เคาน์เตอร์ปิดจะหายไป
         * จากหน้านี้ทั้งที่ token ของมันยังใช้งานได้ — ไม่มีใครเพิกถอนได้อีก
         */
        return CardReaderDevice::with('counter.branch')
            ->active()
            ->whereHas('counter', fn ($q) => $q->whereIn('branch_id', $this->branchIdsInScope()))
            ->orderByDesc('id')
            ->get();
    }

    public function getCountersProperty()
    {
        return $this->countersInScope()->get();
    }

    /**
     * สาขาที่ถูกล็อกไว้ให้ผู้ใช้คนนี้ — null ถ้าเขาเห็นได้หลายสาขา
     *
     * ใช้โชว์ให้เห็นว่าหน้านี้กำลังตั้งค่าของสาขาไหน staff จะได้ไม่ต้องเดา
     * ว่าทำไมไม่มีสาขาอื่นให้เลือก
     */
    public function getLockedBranchProperty(): ?Branch
    {
        $ids = $this->branchIdsInScope();

        return count($ids) === 1 ? Branch::find(reset($ids)) : null;
    }

    /**
     * เคาน์เตอร์ที่ผู้ใช้คนนี้ผูกเครื่องเข้าไปได้
     */
    private function countersInScope()
    {
        return Counter::where('is_active', true)->with('branch')
            ->whereIn('branch_id', $this->branchIdsInScope())
            ->orderBy('branch_id')->orderBy('counter_name');
    }

    /**
     * สาขาที่ผู้ใช้คนนี้ยุ่งกับเครื่องอ่านได้
     *
     * ทั้ง staff และ branch_manager เข้าหน้านี้ได้ ถ้าไม่จำกัด เขาจะผูกเครื่อง
     * เข้ากับเคาน์เตอร์ของสาขาอื่นได้ แล้วยิงข้อมูลบัตรเข้าไปโผล่ที่หน้าจอ
     * ของสาขานั้น
     *
     * ยืมขอบเขตเดียวกับที่ทุกหน้าใช้ — staff ตามสาขาที่ "ลงเวร" อยู่
     * (session working_branch_id, dropdown สาขาทำงานใน sidebar) ไม่ใช่สาขา
     * ต้นสังกัด เพราะเครื่องอ่านตั้งอยู่ตรงหน้าเขาจริงที่สาขานั้น
     *
     * @return array<int, int>
     */
    private function branchIdsInScope(): array
    {
        return Auth::user()?->getVisibleBranchIds() ?? [];
    }

    /**
     * เคาน์เตอร์ที่ควรขึ้นมาให้ตั้งแต่เปิดหน้า
     *
     * คนที่ลงเวรอยู่แล้วไม่ต้องเลือกซ้ำ — เครื่องที่เขากำลังจะตั้ง คือเครื่อง
     * ที่วางอยู่บนโต๊ะตรงหน้าเขา ถ้าเคาน์เตอร์ที่ลงเวรไม่อยู่ในขอบเขต
     * (ย้ายสาขาแล้ว session ยังค้าง) ให้ว่างไว้ ดีกว่าเดาผิดเงียบๆ
     */
    private function defaultCounterId(): string
    {
        $working = (int) session('working_counter_id');

        if ($working === 0 || ! $this->countersInScope()->whereKey($working)->exists()) {
            return '';
        }

        return (string) $working;
    }

    public function render()
    {
        return view('livewire.card-reader.device-manager');
    }
}
