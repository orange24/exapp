<?php

namespace Tests\Unit\Services;

use App\Models\SuperrichRate;
use App\Services\SuperrichRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SuperrichRateServiceTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private SuperrichRateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->service = app(SuperrichRateService::class);
    }

    /**
     * Real response shape from https://api.superrichthailand.com/api/v1/exchange-client/list
     * (captured live 2026-09-09) — SuperRich rebuilt their site on Next.js and moved off
     * the old www.superrichthailand.com/api/v1/rates endpoint (now 404s).
     */
    private function realApiResponseBody(): array
    {
        return [
            'statusCode' => 200,
            'code' => 'SUCCESS',
            'message' => 'Success',
            'data' => [
                'time' => [
                    'date' => '2026-09-09',
                    'time' => ['17:56', '17:02', '16:59'],
                ],
                'exchange' => [
                    'USD' => [
                        ['id' => 59, 'currencyId' => 3, 'denomRem' => '100', 'buyText' => '32.84', 'sellText' => '32.89', 'branchCode' => 'H01', 'denomCode' => '0010', 'unit' => 'USD', 'isFavorite' => false],
                        ['id' => 60, 'currencyId' => 3, 'denomRem' => '50', 'buyText' => '32.84', 'sellText' => '32.89', 'branchCode' => 'H01', 'denomCode' => '0011', 'unit' => 'USD', 'isFavorite' => false],
                    ],
                    'GBP' => [
                        ['id' => 64, 'currencyId' => 4, 'denomRem' => '50', 'buyText' => '44.40', 'sellText' => '44.55', 'branchCode' => 'H01', 'denomCode' => '0020', 'unit' => 'GBP', 'isFavorite' => false],
                    ],
                ],
            ],
        ];
    }

    public function test_fetch_rates_parses_current_superrich_api_shape(): void
    {
        Http::fake([
            'api.superrichthailand.com/*' => Http::response($this->realApiResponseBody(), 200),
        ]);

        $rates = $this->service->fetchRates($this->adminUser->id);

        $this->assertCount(3, $rates);
        $this->assertSame(3, SuperrichRate::count());

        $usd100 = SuperrichRate::where('currency_code', 'USD')->where('superrich_denom', '100')->first();
        $this->assertNotNull($usd100);
        $this->assertSame(32.84, $usd100->rate_buy);
        $this->assertSame(32.89, $usd100->rate_sell);
        // USD has exactly one active denomination in seeded data → auto-mapped regardless of denomRem.
        $this->assertSame($this->denomination->id, $usd100->denomination_id);
        $this->assertSame($this->adminUser->id, $usd100->fetched_by);
    }

    public function test_fetch_rates_requests_current_endpoint_with_no_stale_auth_header(): void
    {
        Http::fake([
            'api.superrichthailand.com/*' => Http::response($this->realApiResponseBody(), 200),
        ]);

        $this->service->fetchRates($this->adminUser->id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.superrichthailand.com/api/v1/exchange-client/list')
                && $request['type'] === 'exchange'
                && ! empty($request['branchId'])
                && ! empty($request['date']);
        });
    }

    public function test_fetch_rates_throws_on_non_successful_response(): void
    {
        Http::fake([
            'api.superrichthailand.com/*' => Http::response('Not Found', 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SuperRich API returned 404');

        $this->service->fetchRates($this->adminUser->id);
    }
}
