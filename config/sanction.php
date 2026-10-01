<?php

return [

    /*
    | ที่อยู่หน้าเว็บสาธารณะของ ปปง. — แยกเป็น config เพราะถ้าวันหน้าย้าย URL
    | จะได้แก้ที่เดียวโดยไม่ต้อง deploy โค้ดใหม่ (ตั้งผ่าน env ได้)
    */
    'amlo' => [
        'base_url' => env('SANCTION_AMLO_BASE_URL', 'https://aps.amlo.go.th/aps/public'),

        'lists' => [
            'freeze_05_th' => 'thailandlist',
            'freeze_04_un' => 'unlist',
        ],

        // มารยาทกับ server หน่วยงานราชการ
        'request_delay_ms' => (int) env('SANCTION_REQUEST_DELAY_MS', 1000),
        'timeout_seconds' => (int) env('SANCTION_TIMEOUT_SECONDS', 30),
        'retry_times' => (int) env('SANCTION_RETRY_TIMES', 3),
        'retry_base_delay_ms' => (int) env('SANCTION_RETRY_BASE_DELAY_MS', 2000),

        // ใส่ไว้ใน User-Agent เพื่อให้ ปปง. ติดต่อกลับได้แทนที่จะบล็อก IP เราทิ้ง
        'contact_email' => env('SANCTION_SYNC_CONTACT_EMAIL', ''),
    ],

    /*
    | Sanity check — ตัวกันตายของระบบทั้งก้อน
    | วันที่ ปปง. เปลี่ยน layout หน้าเว็บ parser จะอ่านได้ไม่กี่แถว
    | ถ้าเขียนทับตามที่ parse ได้ รายชื่อจะหายเกือบหมดในคืนเดียว
    | แล้วทุกคนจะผ่านฉลุยโดยระบบยังดูทำงานปกติทุกประการ
    */
    'sanity' => [
        'min_ratio_of_previous' => (float) env('SANCTION_MIN_RATIO', 0.80),
        'max_detail_failure_ratio' => (float) env('SANCTION_MAX_DETAIL_FAILURE_RATIO', 0.10),
    ],

];
