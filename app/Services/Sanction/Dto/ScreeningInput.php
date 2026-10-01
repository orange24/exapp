<?php

namespace App\Services\Sanction\Dto;

use App\Models\Customer;

class ScreeningInput
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $idType = null,       // passport | national_id
        public readonly ?string $idNumber = null,
        public readonly ?string $nationality = null,
        public readonly ?string $dob = null,          // Y-m-d
    ) {
    }

    public static function fromCustomer(Customer $customer): self
    {
        $name = $customer->name_en
            ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));

        if (trim($name) === '') {
            $name = (string) $customer->name_th;
        }

        return new self(
            name: trim($name) !== '' ? trim($name) : null,
            idType: $customer->id_type,
            idNumber: $customer->id_number,
            nationality: $customer->nationality,
            dob: $customer->date_of_birth?->format('Y-m-d'),
        );
    }

    /** ไม่มีอะไรให้ตรวจเลย — รายการนี้จะเข้ารายงานช่องโหว่การตรวจ */
    public function isEmpty(): bool
    {
        return trim((string) $this->name) === '' && trim((string) $this->idNumber) === '';
    }
}
