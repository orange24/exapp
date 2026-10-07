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
        CardReaderDevice::whereNull('revoked_at')->where('id', $id)
            ->update(['revoked_at' => now()]);

        session()->flash('success', 'เพิกถอนเครื่องแล้ว — เครื่องนั้นส่งข้อมูลเข้าระบบไม่ได้อีก');
    }

    public function dismissToken(): void
    {
        $this->issuedToken = '';
        $this->issuedFor = '';
    }

    public function getDevicesProperty()
    {
        return CardReaderDevice::with('counter.branch')
            ->orderByRaw('revoked_at is null desc')
            ->orderByDesc('id')
            ->get();
    }

    public function getCountersProperty()
    {
        return Counter::where('is_active', true)->with('branch')
            ->orderBy('branch_id')->orderBy('counter_name')->get();
    }

    public function render()
    {
        return view('livewire.card-reader.device-manager');
    }
}
