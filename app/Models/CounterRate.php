<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CounterRate extends Model
{
    protected $fillable = [
        'counter_id', 'currency_code', 'denomination_id', 'rate_buy', 'rate_sell',
        'sell_discount_rate', 'set_by', 'rate_date',
    ];

    protected function casts(): array
    {
        return ['rate_date' => 'datetime'];
    }

    /**
     * เขียนราคาของ เคาน์เตอร์ + ชนิดธนบัตร + วัน หนึ่งแถว
     *
     * ตารางมีกุญแจกันซ้ำที่ (counter_id, denomination_id, rate_date) แล้ว ถ้า
     * สองคำขอวิ่งพร้อมกันและต่างฝ่ายต่างเห็นว่า "ยังไม่มี" ฝ่ายที่แพ้จะชนกุญแจ
     * ซึ่งถูกต้องในแง่ข้อมูล แต่ผู้ใช้ที่แค่กดบันทึกรัวไปสองทีไม่ควรเจอหน้า error
     * จึงกลืนการชนแล้วทำเป็นแก้ไขแถวที่อีกฝ่ายเพิ่งสร้างแทน
     *
     * ทุกทางที่เขียนราคาต้องผ่านตรงนี้ ไม่ใช่เรียก updateOrCreate เอง
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public static function putRate(array $keys, array $values): self
    {
        try {
            return static::updateOrCreate($keys, $values);
        } catch (UniqueConstraintViolationException) {
            // อีกฝ่ายเพิ่งสร้างแถวนี้ไปเสี้ยววินาทีก่อน — แก้ของเขาแทน
            $existing = static::where($keys)->firstOrFail();
            $existing->fill($values)->save();

            return $existing;
        }
    }
    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function setBy()
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
