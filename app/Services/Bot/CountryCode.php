<?php

namespace App\Services\Bot;

use Locale;

/**
 * แปลงค่าสัญชาติที่เก็บในระบบเป็นรหัสประเทศ 2 ตัว (ISO 3166-1 alpha-2)
 * ตาม dropdown "สัญชาติ" ของ template แบงค์ชาติ
 *
 * ค่าในฐานข้อมูลมาได้หลายทาง — อ่านจากชิปพาสปอร์ต/MRZ ได้รหัส 3 ตัว (THA),
 * พนักงานพิมพ์เองได้ชื่อภาษาไทย (ไทย) หรืออังกฤษ (Singapore) — จึงรับทุกแบบ
 */
class CountryCode
{
    /** alpha-3 → alpha-2 ครบทุกรหัสที่อยู่ใน template */
    private const ALPHA3 = [
        'AFG' => 'AF', 'ALA' => 'AX', 'ALB' => 'AL', 'DZA' => 'DZ', 'ASM' => 'AS', 'AND' => 'AD', 'AGO' => 'AO',
        'AIA' => 'AI', 'ATA' => 'AQ', 'ATG' => 'AG', 'ARG' => 'AR', 'ARM' => 'AM', 'ABW' => 'AW', 'AUS' => 'AU',
        'AUT' => 'AT', 'AZE' => 'AZ', 'BHS' => 'BS', 'BHR' => 'BH', 'BGD' => 'BD', 'BRB' => 'BB', 'BLR' => 'BY',
        'BEL' => 'BE', 'BLZ' => 'BZ', 'BEN' => 'BJ', 'BMU' => 'BM', 'BTN' => 'BT', 'BOL' => 'BO', 'BES' => 'BQ',
        'BIH' => 'BA', 'BWA' => 'BW', 'BVT' => 'BV', 'BRA' => 'BR', 'IOT' => 'IO', 'BRN' => 'BN', 'BGR' => 'BG',
        'BFA' => 'BF', 'BDI' => 'BI', 'CPV' => 'CV', 'KHM' => 'KH', 'CMR' => 'CM', 'CAN' => 'CA', 'CYM' => 'KY',
        'CAF' => 'CF', 'TCD' => 'TD', 'CHL' => 'CL', 'CHN' => 'CN', 'CXR' => 'CX', 'CCK' => 'CC', 'COL' => 'CO',
        'COM' => 'KM', 'COG' => 'CG', 'COD' => 'CD', 'COK' => 'CK', 'CRI' => 'CR', 'CIV' => 'CI', 'HRV' => 'HR',
        'CUB' => 'CU', 'CUW' => 'CW', 'CYP' => 'CY', 'CZE' => 'CZ', 'DNK' => 'DK', 'DJI' => 'DJ', 'DMA' => 'DM',
        'DOM' => 'DO', 'ECU' => 'EC', 'EGY' => 'EG', 'SLV' => 'SV', 'GNQ' => 'GQ', 'ERI' => 'ER', 'EST' => 'EE',
        'SWZ' => 'SZ', 'ETH' => 'ET', 'FLK' => 'FK', 'FRO' => 'FO', 'FJI' => 'FJ', 'FIN' => 'FI', 'FRA' => 'FR',
        'GUF' => 'GF', 'PYF' => 'PF', 'ATF' => 'TF', 'GAB' => 'GA', 'GMB' => 'GM', 'GEO' => 'GE', 'DEU' => 'DE',
        'D' => 'DE', 'GHA' => 'GH', 'GIB' => 'GI', 'GRC' => 'GR', 'GRL' => 'GL', 'GRD' => 'GD', 'GLP' => 'GP',
        'GUM' => 'GU', 'GTM' => 'GT', 'GGY' => 'GG', 'GIN' => 'GN', 'GNB' => 'GW', 'GUY' => 'GY', 'HTI' => 'HT',
        'HMD' => 'HM', 'VAT' => 'VA', 'HND' => 'HN', 'HKG' => 'HK', 'HUN' => 'HU', 'ISL' => 'IS', 'IND' => 'IN',
        'IDN' => 'ID', 'IRN' => 'IR', 'IRQ' => 'IQ', 'IRL' => 'IE', 'IMN' => 'IM', 'ISR' => 'IL', 'ITA' => 'IT',
        'JAM' => 'JM', 'JPN' => 'JP', 'JEY' => 'JE', 'JOR' => 'JO', 'KAZ' => 'KZ', 'KEN' => 'KE', 'KIR' => 'KI',
        'PRK' => 'KP', 'KOR' => 'KR', 'KWT' => 'KW', 'KGZ' => 'KG', 'LAO' => 'LA', 'LVA' => 'LV', 'LBN' => 'LB',
        'LSO' => 'LS', 'LBR' => 'LR', 'LBY' => 'LY', 'LIE' => 'LI', 'LTU' => 'LT', 'LUX' => 'LU', 'MAC' => 'MO',
        'MDG' => 'MG', 'MWI' => 'MW', 'MYS' => 'MY', 'MDV' => 'MV', 'MLI' => 'ML', 'MLT' => 'MT', 'MHL' => 'MH',
        'MTQ' => 'MQ', 'MRT' => 'MR', 'MUS' => 'MU', 'MYT' => 'YT', 'MEX' => 'MX', 'FSM' => 'FM', 'MDA' => 'MD',
        'MCO' => 'MC', 'MNG' => 'MN', 'MNE' => 'ME', 'MSR' => 'MS', 'MAR' => 'MA', 'MOZ' => 'MZ', 'MMR' => 'MM',
        'NAM' => 'NA', 'NRU' => 'NR', 'NPL' => 'NP', 'NLD' => 'NL', 'NCL' => 'NC', 'NZL' => 'NZ', 'NIC' => 'NI',
        'NER' => 'NE', 'NGA' => 'NG', 'NIU' => 'NU', 'NFK' => 'NF', 'MKD' => 'MK', 'MNP' => 'MP', 'NOR' => 'NO',
        'OMN' => 'OM', 'PAK' => 'PK', 'PLW' => 'PW', 'PSE' => 'PS', 'PAN' => 'PA', 'PNG' => 'PG', 'PRY' => 'PY',
        'PER' => 'PE', 'PHL' => 'PH', 'PCN' => 'PN', 'POL' => 'PL', 'PRT' => 'PT', 'PRI' => 'PR', 'QAT' => 'QA',
        'REU' => 'RE', 'ROU' => 'RO', 'RUS' => 'RU', 'RWA' => 'RW', 'BLM' => 'BL', 'SHN' => 'SH', 'KNA' => 'KN',
        'LCA' => 'LC', 'MAF' => 'MF', 'SPM' => 'PM', 'VCT' => 'VC', 'WSM' => 'WS', 'SMR' => 'SM', 'STP' => 'ST',
        'SAU' => 'SA', 'SEN' => 'SN', 'SRB' => 'RS', 'SYC' => 'SC', 'SLE' => 'SL', 'SGP' => 'SG', 'SXM' => 'SX',
        'SVK' => 'SK', 'SVN' => 'SI', 'SLB' => 'SB', 'SOM' => 'SO', 'ZAF' => 'ZA', 'SGS' => 'GS', 'SSD' => 'SS',
        'ESP' => 'ES', 'LKA' => 'LK', 'SDN' => 'SD', 'SUR' => 'SR', 'SJM' => 'SJ', 'SWE' => 'SE', 'CHE' => 'CH',
        'SYR' => 'SY', 'TWN' => 'TW', 'TJK' => 'TJ', 'TZA' => 'TZ', 'THA' => 'TH', 'TLS' => 'TL', 'TGO' => 'TG',
        'TKL' => 'TK', 'TON' => 'TO', 'TTO' => 'TT', 'TUN' => 'TN', 'TUR' => 'TR', 'TKM' => 'TM', 'TCA' => 'TC',
        'TUV' => 'TV', 'UGA' => 'UG', 'UKR' => 'UA', 'ARE' => 'AE', 'GBR' => 'GB', 'USA' => 'US', 'UMI' => 'UM',
        'URY' => 'UY', 'UZB' => 'UZ', 'VUT' => 'VU', 'VEN' => 'VE', 'VNM' => 'VN', 'VGB' => 'VG', 'VIR' => 'VI',
        'WLF' => 'WF', 'ESH' => 'EH', 'YEM' => 'YE', 'ZMB' => 'ZM', 'ZWE' => 'ZW',
        // รหัสพิเศษบนพาสปอร์ตที่ ICAO ใช้แทนรหัส ISO
        'GBD' => 'GB', 'GBN' => 'GB', 'GBO' => 'GB', 'GBS' => 'GB', 'GBP' => 'GB', 'RKS' => 'XK',
    ];

    /** คำที่คนพิมพ์บ่อยแต่ไม่ใช่ชื่อประเทศตรงตัว */
    private const ALIASES = [
        'THAI' => 'TH', 'THAILAND' => 'TH', 'คนไทย' => 'TH',
        'UK' => 'GB', 'ENGLAND' => 'GB', 'BRITISH' => 'GB', 'อังกฤษ' => 'GB',
        'USA' => 'US', 'AMERICA' => 'US', 'AMERICAN' => 'US', 'อเมริกา' => 'US',
        'KOREA' => 'KR', 'SOUTH KOREA' => 'KR', 'เกาหลี' => 'KR', 'เกาหลีใต้' => 'KR',
        'CHINESE' => 'CN', 'จีน' => 'CN', 'JAPANESE' => 'JP', 'ญี่ปุ่น' => 'JP',
        'RUSSIA' => 'RU', 'RUSSIAN' => 'RU', 'รัสเซีย' => 'RU',
        'GERMAN' => 'DE', 'FRENCH' => 'FR', 'INDIAN' => 'IN', 'AUSTRALIAN' => 'AU',
        'VIETNAM' => 'VN', 'LAOS' => 'LA', 'ลาว' => 'LA', 'พม่า' => 'MM', 'กัมพูชา' => 'KH',
    ];

    /**
     * @param  array<int, string>  $allowed  รหัส 2 ตัวที่ template รับ
     */
    public function __construct(private array $allowed)
    {
    }

    public function toAlpha2(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $upper = mb_strtoupper($value);

        $candidate = match (true) {
            strlen($upper) === 2 && ctype_alpha($upper) => $upper,
            isset(self::ALPHA3[$upper]) => self::ALPHA3[$upper],
            isset(self::ALIASES[$upper]) => self::ALIASES[$upper],
            default => $this->byName($upper),
        };

        return $candidate !== null && in_array($candidate, $this->allowed, true) ? $candidate : null;
    }

    /**
     * ชื่อประเทศเต็ม ภาษาอังกฤษหรือไทย เทียบกับชื่อจาก ICU (ext-intl)
     */
    private function byName(string $upper): ?string
    {
        foreach ($this->allowed as $code) {
            foreach (['en', 'th'] as $lang) {
                if (mb_strtoupper(Locale::getDisplayRegion('-' . $code, $lang)) === $upper) {
                    return $code;
                }
            }
        }

        return null;
    }
}
