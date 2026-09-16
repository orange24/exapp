<?php

namespace App\Models;

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
     * ใช้ช่วงเวลาตาม WORKING_CUT_OFF เหมือนที่ closeDay / ThbCashService ใช้
     * เพื่อให้ "วันนี้" หมายถึงวันทำการเดียวกันทั้งระบบ
     *
     * @return array{has_open_day: bool, transaction_count: int}
     */
    public function workingDayActivity(): array
    {
        $date = now()->format('Y-m-d');
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');

        $dayStart = \Carbon\Carbon::parse("{$date} {$cutoff}");
        $dayEnd = $dayStart->copy()->addDay();

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
