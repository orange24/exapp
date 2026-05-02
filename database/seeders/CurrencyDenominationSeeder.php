<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencyDenominationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seq = 0;
        $data = [
            // [country, currency_code, currency_name, currency_name_th, denom_label, buy, sell]
            ['United States',         'USD',  'US Dollar',             'ดอลลาร์สหรัฐ',         '100-50',       32.230000, 32.530000],
            ['United States',         'USD',  'US Dollar',             'ดอลลาร์สหรัฐ',         '20-10',        32.170000, 32.480000],
            ['United States',         'USD',  'US Dollar',             'ดอลลาร์สหรัฐ',         '5',            32.170000, 32.480000],
            ['United States',         'USD',  'US Dollar',             'ดอลลาร์สหรัฐ',         '2-1',          31.970000, 32.380000],
            ['United Kingdom',        'GBP',  'British Pound',         'ปอนด์อังกฤษ',          '50',           43.830000, 44.180000],
            ['United Kingdom',        'GBP',  'British Pound',         'ปอนด์อังกฤษ',          '20-5',         43.830000, 44.180000],
            ['European Union',        'EUR',  'Euro',                  'ยูโร',                  '500-100',      37.780000, 38.130000],
            ['European Union',        'EUR',  'Euro',                  'ยูโร',                  '50-5',         37.780000, 38.130000],
            ['Switzerland',           'CHF',  'Swiss Franc',           'ฟรังก์สวิส',            '1000-10',      41.230000, 41.530000],
            ['Australia',             'AUD',  'Australian Dollar',     'ดอลลาร์ออสเตรเลีย',    '100-5',        23.030000, 23.380000],
            ['Japan',                 'JPY',  'Japanese Yen',          'เยนญี่ปุ่น',            '10000-1000',   0.204500,  0.206800],
            ['Malaysia',              'MYR',  'Malaysian Ringgit',     'ริงกิตมาเลเซีย',       '100-50',       8.080000,  8.210000],
            ['Malaysia',              'MYR',  'Malaysian Ringgit',     'ริงกิตมาเลเซีย',       '20-5',         7.750000,  8.110000],
            ['Malaysia',              'MYR',  'Malaysian Ringgit',     'ริงกิตมาเลเซีย',       '1',            6.600000,  7.910000],
            ['Singapore',             'SGD',  'Singapore Dollar',      'ดอลลาร์สิงคโปร์',      '1000',         28.080000, 28.430000],
            ['Singapore',             'SGD',  'Singapore Dollar',      'ดอลลาร์สิงคโปร์',      '100-50',       25.230000, 25.530000],
            ['Singapore',             'SGD',  'Singapore Dollar',      'ดอลลาร์สิงคโปร์',      '20-5',         25.230000, 25.530000],
            ['Singapore',             'SGD',  'Singapore Dollar',      'ดอลลาร์สิงคโปร์',      '2',            25.130000, 25.530000],
            ['Hong Kong',             'HKD',  'Hong Kong Dollar',      'ดอลลาร์ฮ่องกง',        '1000-10',      4.090000,  4.173000],
            ['Canada',                'CAD',  'Canadian Dollar',       'ดอลลาร์แคนาดา',        '100-5',        23.630000, 23.930000],
            ['Denmark',               'DKK',  'Danish Krone',          'โครนเดนมาร์ก',         '500-50',       4.550000,  4.803000],
            ['Norway',                'NOK',  'Norwegian Krone',       'โครนนอร์เวย์',          '500-50',       2.800000,  3.103000],
            ['Sweden',                'SEK',  'Swedish Krona',         'โครนสวีเดน',            '500-20',       2.850000,  3.153000],
            ['Taiwan',                'TWD',  'Taiwan Dollar',         'ดอลลาร์ไต้หวัน',       '2000-100',     0.985000,  1.013000],
            ['Korea',                 'KRW',  'Korean Won',            'วอนเกาหลี',            '50000-5000',   0.021900,  0.022530],
            ['Korea',                 'KRW',  'Korean Won',            'วอนเกาหลี',            '1000',         0.021200,  0.022030],
            ['China',                 'CNY',  'Chinese Yuan',          'หยวนจีน',               '100-50',       4.690000,  4.763000],
            ['China',                 'CNY',  'Chinese Yuan',          'หยวนจีน',               '20-10',        4.560000,  4.713000],
            ['China',                 'CNY',  'Chinese Yuan',          'หยวนจีน',               '5-1',          4.050000,  4.613000],
            ['Philippines',           'PHP',  'Philippine Peso',       'เปโซฟิลิปปินส์',       '1000-20',      0.510000,  0.538000],
            ['New Zealand',           'NZD',  'New Zealand Dollar',    'ดอลลาร์นิวซีแลนด์',    '100-5',        18.830000, 19.180000],
            ['Saudi Arabia',          'SAR',  'Saudi Riyal',           'รียาลซาอุดีอาระเบีย',  '500-1',        8.300000,  8.730000],
            ['United Arab Emirates',  'AED',  'UAE Dirham',            'เดอร์แฮมยูเออี',       '1000-50',      8.450000,  8.880000],
            ['Qatar',                 'QAR',  'Qatari Riyal',         'รียาลกาตาร์',           '500-50',       8.250000,  8.730000],
            ['Oman',                  'OMR',  'Omani Rial',           'เรียลโอมาน',            '50-1',         80.500000, 83.530000],
            ['Bahrain',               'BHD',  'Bahraini Dinar',       'ดีนาร์บาห์เรน',        '20-1',         81.000000, 84.030000],
            ['Vietnam',               'VND',  'Vietnamese Dong',      'ดองเวียดนาม',           '500000-10000', 0.001160,  0.001280],
            ['Brunei',                'BND',  'Brunei Dollar',        'ดอลลาร์บรูไน',         '1000-1',       24.450000, 25.480000],
            ['Kuwait',                'KWD',  'Kuwaiti Dinar',        'ดีนาร์คูเวต',          '20-1',         98.500000, 103.030000],
            ['South Africa',          'ZAR',  'South African Rand',   'แรนด์แอฟริกาใต้',      '200-10',       1.600000,  1.990000],
            ['Indonesia',             'IDR',  'Indonesian Rupiah',    'รูเปียห์อินโดนีเซีย',  '100000-1000',  0.001750,  0.001970],
            ['India',                 'INR',  'Indian Rupee',         'รูปีอินเดีย',           '500',          0.332000,  0.353000],
            ['Scotland',              'SCOT', 'Scottish Pound',       'ปอนด์สกอตแลนด์',       '100-5',        41.000000, 42.530000],
            ['Russia',                'RUB',  'Russian Ruble',        'รูเบิลรัสเซีย',        '5000-10',      0.401000,  0.428000],
            ['Macau',                 'MOP',  'Macanese Pataca',      'ปาตากามาเก๊า',         '1000-10',      3.000000,  4.130000],
            ['Israel',                'ILS',  'Israeli New Shekel',   'เชเกลอิสราเอล',        '200-50',       7.100000,  10.080000],
            ['Turkey',                'TRY',  'Turkish Lira',         'ลีราตุรกี',             '200-5',        0.550000,  0.810000],
            ['Jordan',                'JOD',  'Jordanian Dinar',      'ดีนาร์จอร์แดน',        '50-1',         41.000000, 44.030000],
            ['Pakistan',              'PKR',  'Pakistani Rupee',      'รูปีปากีสถาน',         '5000-50',      0.045000,  0.140000],
        ];

        foreach ($data as $row) {
            [$country, $code, $name, $nameTh, $denom, $buy, $sell] = $row;
            $seq++;

            // Ensure currency exists
            \App\Models\Currency::updateOrCreate(
                ['currency_code' => $code],
                [
                    'currency_name'    => $name,
                    'currency_name_th' => $nameTh,
                    'country'          => $country,
                    'country_flag'     => strtolower($code) . '.png',
                    'seq'              => $seq,
                    'is_active'        => true,
                    'target_currency'  => 'THB',
                ]
            );

            // Create denomination (no rates — rates are set per counter)
            \App\Models\CurrencyDenomination::updateOrCreate(
                ['currency_code' => $code, 'denom_label' => $denom],
                [
                    'display_name' => $code . ' ' . $denom,
                    'seq'          => $seq,
                    'is_active'    => true,
                ]
            );
        }
    }
}
