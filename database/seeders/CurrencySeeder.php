<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar',          'name_th' => 'ดอลลาร์สหรัฐ',    'country' => 'United States',  'buy' => 35.00, 'sell' => 35.80, 'seq' => 1],
            ['code' => 'EUR', 'name' => 'Euro',                'name_th' => 'ยูโร',             'country' => 'European Union', 'buy' => 37.00, 'sell' => 38.00, 'seq' => 2],
            ['code' => 'GBP', 'name' => 'British Pound',       'name_th' => 'ปอนด์อังกฤษ',     'country' => 'United Kingdom', 'buy' => 44.00, 'sell' => 45.50, 'seq' => 3],
            ['code' => 'JPY', 'name' => 'Japanese Yen',        'name_th' => 'เยนญี่ปุ่น',      'country' => 'Japan',          'buy' => 0.22,  'sell' => 0.24,  'seq' => 4],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',        'name_th' => 'หยวนจีน',         'country' => 'China',          'buy' => 4.70,  'sell' => 4.90,  'seq' => 5],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar',    'name_th' => 'ดอลลาร์ฮ่องกง',  'country' => 'Hong Kong',      'buy' => 4.40,  'sell' => 4.60,  'seq' => 6],
            ['code' => 'SGD', 'name' => 'Singapore Dollar',    'name_th' => 'ดอลลาร์สิงคโปร์', 'country' => 'Singapore',      'buy' => 25.50, 'sell' => 26.50, 'seq' => 7],
            ['code' => 'AUD', 'name' => 'Australian Dollar',   'name_th' => 'ดอลลาร์ออสเตรเลีย','country' => 'Australia',     'buy' => 22.00, 'sell' => 23.00, 'seq' => 8],
            ['code' => 'CHF', 'name' => 'Swiss Franc',         'name_th' => 'ฟรังก์สวิส',      'country' => 'Switzerland',    'buy' => 38.00, 'sell' => 39.50, 'seq' => 9],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',     'name_th' => 'ดอลลาร์แคนาดา',  'country' => 'Canada',         'buy' => 25.00, 'sell' => 26.00, 'seq' => 10],
            ['code' => 'SEK', 'name' => 'Swedish Krona',       'name_th' => 'โครนสวีเดน',      'country' => 'Sweden',         'buy' => 3.20,  'sell' => 3.50,  'seq' => 11],
            ['code' => 'NOK', 'name' => 'Norwegian Krone',     'name_th' => 'โครนนอร์เวย์',    'country' => 'Norway',         'buy' => 3.10,  'sell' => 3.40,  'seq' => 12],
            ['code' => 'DKK', 'name' => 'Danish Krone',        'name_th' => 'โครนเดนมาร์ก',    'country' => 'Denmark',        'buy' => 4.90,  'sell' => 5.20,  'seq' => 13],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar',  'name_th' => 'ดอลลาร์นิวซีแลนด์','country' => 'New Zealand',  'buy' => 20.00, 'sell' => 21.00, 'seq' => 14],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit',   'name_th' => 'ริงกิตมาเลเซีย',  'country' => 'Malaysia',       'buy' => 7.50,  'sell' => 7.90,  'seq' => 15],
        ];

        foreach ($currencies as $c) {
            \App\Models\Currency::firstOrCreate(
                ['currency_code' => $c['code']],
                [
                    'currency_name'    => $c['name'],
                    'currency_name_th' => $c['name_th'],
                    'country'          => $c['country'],
                    'country_flag'     => strtolower($c['code']) . '.png',
                    'rate_buy'         => $c['buy'],
                    'rate_sell'        => $c['sell'],
                    'seq'              => $c['seq'],
                    'is_active'        => true,
                    'target_currency'  => 'THB',
                ]
            );
        }
    }
}
