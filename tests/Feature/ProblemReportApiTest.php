<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProblemReportApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('user', function ($table) {
            $table->id('user_id');
            $table->string('username', 100);
            $table->string('role');
        });

        Schema::create('stall', function ($table) {
            $table->id('stall_id');
            $table->string('stall_number', 20);
        });

        Schema::create('problem_report', function ($table) {
            $table->id('problem_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('stall_id');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->dateTime('report_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_comment')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('problem_report');
        Schema::dropIfExists('stall');
        Schema::dropIfExists('user');

        parent::tearDown();
    }

    public function test_it_can_list_problem_reports_and_update_status(): void
    {
        $userId = \DB::table('user')->insertGetId([
            'username' => 'นภา',
            'role' => 'seller',
        ]);

        $stallId = \DB::table('stall')->insertGetId([
            'stall_number' => 'A12',
        ]);

        $problemId = \DB::table('problem_report')->insertGetId([
            'user_id' => $userId,
            'stall_id' => $stallId,
            'description' => 'น้ำรั่ว',
            'image' => null,
            'report_date' => now(),
            'status' => 'pending',
            'admin_comment' => null,
        ]);

        $response = $this->getJson('/api/v1/admin/problem-reports');

        $response->assertOk()
            ->assertJsonPath('data.0.description', 'น้ำรั่ว')
            ->assertJsonPath('data.0.user_name', 'นภา')
            ->assertJsonPath('data.0.stall_number', 'A12');

        $updateResponse = $this->putJson('/api/v1/admin/problem-reports/' . $problemId, [
            'status' => 'progress',
            'admin_note' => 'กำลังตรวจสอบ',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.status', 'progress')
            ->assertJsonPath('data.admin_comment', 'กำลังตรวจสอบ');
    }
}
