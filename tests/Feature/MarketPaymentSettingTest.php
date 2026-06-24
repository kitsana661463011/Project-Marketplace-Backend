<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\MarketPaymentSettingController;
use App\Models\MarketPaymentSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketPaymentSettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('market_payment_settings', function ($table) {
            $table->id();
            $table->string('account_name', 100);
            $table->string('account_number', 50);
            $table->string('qr_code_path')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('market_payment_settings');

        parent::tearDown();
    }

    public function test_it_can_fetch_the_latest_payment_settings(): void
    {
        MarketPaymentSetting::create([
            'account_name' => 'ตลาดมั่งมี',
            'account_number' => '0123456789',
            'qr_code_path' => null,
        ]);

        $response = $this->getJson('/api/admin/market-payment-settings');

        $response->assertOk()
            ->assertJsonPath('data.account_name', 'ตลาดมั่งมี')
            ->assertJsonPath('data.account_number', '0123456789');
    }

    public function test_it_can_store_a_qr_code_with_a_normalized_path(): void
    {
        $controller = new MarketPaymentSettingController();
        $method = new \ReflectionMethod($controller, 'storeUploadedFile');
        $method->setAccessible(true);

        $tempPath = tempnam(sys_get_temp_dir(), 'qr');
        file_put_contents($tempPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAIAAeIhvAAAAAElFTkSuQmCC'));

        $file = new UploadedFile($tempPath, 'qr-code.png', 'image/png', null, true);
        $path = $method->invoke($controller, $file);

        $this->assertNotEmpty($path);
        $this->assertStringContainsString('qr-codes/', $path);
        $this->assertStringNotContainsString('\\', $path);
        $this->assertFileExists(storage_path('app/public/' . $path));
    }
}
