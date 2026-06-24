<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnnouncementApiTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('announcement');
        Schema::dropIfExists('user');

        Schema::create('user', function ($table) {
            $table->id('user_id');
            $table->string('username');
        });

        Schema::create('announcement', function ($table) {
            $table->id('announcement_id');
            $table->string('title', 100);
            $table->enum('announcement_type', ['urgent', 'activity', 'general'])->default('general');
            $table->text('description')->nullable();
            $table->dateTime('publish_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('user_id');
        });
    }

    public function test_admin_can_manage_announcements(): void
    {
        $userId = DB::table('user')->insertGetId([
            'username' => 'admin',
        ]);

        DB::table('announcement')->insert([
            'title' => 'ประกาศทดสอบ',
            'announcement_type' => 'urgent',
            'description' => 'รายละเอียดทดสอบ',
            'publish_date' => now()->toDateTimeString(),
            'status' => 'active',
            'user_id' => $userId,
        ]);

        $listResponse = $this->getJson('/api/admin/announcements');
        $listResponse->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    ['announcement_id', 'title', 'announcement_type', 'description', 'publish_date', 'status', 'user_id', 'user_name'],
                ],
            ]);

        $storeResponse = $this->postJson('/api/admin/announcements', [
            'title' => 'ประกาศใหม่',
            'announcement_type' => 'activity',
            'description' => 'ข้อความใหม่',
            'status' => 'active',
            'user_id' => $userId,
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.title', 'ประกาศใหม่');

        $announcementId = $storeResponse->json('data.announcement_id');

        $updateResponse = $this->putJson('/api/admin/announcements/' . $announcementId, [
            'title' => 'ประกาศแก้ไข',
            'announcement_type' => 'general',
            'description' => 'ข้อความแก้ไข',
            'status' => 'inactive',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.title', 'ประกาศแก้ไข');

        $toggleResponse = $this->patchJson('/api/admin/announcements/' . $announcementId . '/toggle-status');
        $toggleResponse->assertOk()
            ->assertJsonPath('data.status', 'active');

        $deleteResponse = $this->deleteJson('/api/admin/announcements/' . $announcementId);
        $deleteResponse->assertOk();
    }
}
