<?php

namespace App\Models;

use App\Support\WorkingDayClock;
use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    protected $fillable = ['counter_code', 'counter_name', 'branch_id', 'tenant_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function rates()
    {
        return $this->hasMany(CounterRate::class);
    }

    public function latestRates()
    {
        return $this->hasMany(CounterRate::class)
            ->whereDate('rate_date', today())
            ->orWhere(function ($q) {
                $q->whereNull('rate_date');
            })
            ->latest('updated_at');
    }

    /**
     * งานที่ค้างอยู่ที่เคาน์เตอร์นี้ในวันทำการปัจจุบัน
     *
     * ใช้เตือนก่อนเปลี่ยนเคาน์เตอร์ทำงาน — บิลที่บันทึกไปแล้วถูกตราไว้กับ
     * counter_id เดิมและไม่ย้ายตาม การเปลี่ยนจึงมีผลกับรายการถัดไปเท่านั้น
     *
     * ถามช่วงเวลาจาก WorkingDayClock เหมือนทุกจุดในระบบ เพื่อให้ "วันนี้"
     * หมายถึงวันทำการเดียวกันทั้งระบบ ไม่ใช่วันตามปฏิทิน
     *
     * @return array{has_open_day: bool, transaction_count: int}
     */
    public function workingDayActivity(): array
    {
        $clock = app(WorkingDayClock::class);

        $date = $clock->currentDate();
        $dayStart = $clock->dayStart($date);
        $dayEnd = $clock->dayEnd($date);

        return [
            'has_open_day' => WorkingDay::where('counter_id', $this->id)
                ->whereDate('work_date', $date)
                ->where('status', 'open')
                ->exists(),

            'transaction_count' => TransactionMaster::where('counter_id', $this->id)
                ->where('flag_cancel', 'N')
                ->where('trns_datetime', '>', $dayStart)
                ->where('trns_datetime', '<=', $dayEnd)
                ->count(),
        ];
    }
}
