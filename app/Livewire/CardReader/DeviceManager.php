<?php

namespace App\Livewire\CardReader;

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
        $this->counterId = '';
    }

    public function revoke(int $id): void
    {
        $query = CardReaderDevice::whereNull('revoked_at')->where('id', $id);

        if (! Auth::user()?->isAdmin()) {
            $query->whereIn('counter_id', $this->countersInScope()->pluck('counters.id'));
        }

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
         */
        $query = CardReaderDevice::with('counter.branch')
            ->active()
            ->orderByDesc('id');

        if (! Auth::user()?->isAdmin()) {
            $query->whereIn('counter_id', $this->countersInScope()->pluck('counters.id'));
        }

        return $query->get();
    }

    public function getCountersProperty()
    {
        return $this->countersInScope()->get();
    }

    /**
     * เคาน์เตอร์ที่ผู้ใช้คนนี้ผูกเครื่องเข้าไปได้
     *
     * branch_manager ก็ถือ module1/write เหมือน admin จึงเข้าหน้านี้ได้
     * ถ้าไม่จำกัด เขาจะผูกเครื่องเข้ากับเคาน์เตอร์ของสาขาอื่นได้ แล้วยิง
     * ข้อมูลบัตรเข้าไปโผล่ที่หน้าจอของสาขานั้น
     */
    private function countersInScope()
    {
        $query = Counter::where('is_active', true)->with('branch')
            ->orderBy('branch_id')->orderBy('counter_name');

        if (! Auth::user()?->isAdmin()) {
            $query->where('branch_id', Auth::user()?->branch_id);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.card-reader.device-manager');
    }
}
