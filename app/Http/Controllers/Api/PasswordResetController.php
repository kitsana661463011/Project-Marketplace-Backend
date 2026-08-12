<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'กรุณากรอกอีเมลให้ถูกต้อง',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบอีเมลนี้ในระบบ กรุณาตรวจสอบอีเมลของคุณอีกครั้ง',
            ], 404);
        }

        // Generate 6-digit random OTP code
        $code = sprintf('%06d', mt_rand(100000, 999999));

        // Clear old codes for this email
        PasswordResetCode::where('email', $email)->delete();

        // Create new reset code record valid for 15 minutes
        PasswordResetCode::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Send email
        try {
            Mail::html("
                <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
                    <div style='text-align: center; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;'>
                        <h1 style='color: #1e88e5; margin: 0; font-size: 24px;'>MarketPlace</h1>
                    </div>
                    <div style='padding: 20px 0;'>
                        <h2 style='color: #0f172a; font-size: 18px; margin-top: 0;'>สวัสดีคุณ {$user->username}</h2>
                        <p style='color: #475569; font-size: 14px; line-height: 1.6;'>คุณได้ทำรายการขอรีเซ็ตรหัสผ่านสำหรับบัญชี <b>{$email}</b> โปรดใช้รหัสยืนยัน 6 หลักด้านล่างนี้เพื่อตั้งรหัสผ่านใหม่:</p>
                        <div style='background: linear-gradient(135deg, #eff6ff, #dbeafe); padding: 18px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #1d4ed8; border-radius: 12px; margin: 24px 0; border: 1px solid #bfdbfe;'>
                            {$code}
                        </div>
                        <p style='color: #64748b; font-size: 13px; line-height: 1.5;'>⏱️ รหัสยืนยันนี้จะหมดอายุภายใน <b>15 นาที</b> หากคุณไม่ได้เป็นผู้ส่งคำขอนี้ สามารถละเว้นอีเมลฉบับนี้ได้อย่างปลอดภัย</p>
                    </div>
                    <div style='text-align: center; padding-top: 16px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px;'>
                        © MarketPlace App. All rights reserved.
                    </div>
                </div>
            ", function ($message) use ($email) {
                $message->to($email)
                    ->subject('รหัสรีเซ็ตรหัสผ่านสำหรับบัญชี MarketPlace');
            });
        } catch (\Exception $e) {
            Log::error('Failed to send reset password email: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'เกิดข้อผิดพลาดในการส่งอีเมล: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'ส่งรหัสรีเซ็ตรหัสผ่านไปยังอีเมลของคุณเรียบร้อยแล้ว กรุณาเช็กกล่องข้อความในอีเมล',
        ], 200);
    }

    public function verifyResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            ], 422);
        }

        $record = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json([
                'status' => false,
                'message' => 'รหัสยืนยันไม่ถูกต้องหรือหมดอายุแล้ว กรุณากดขอรหัสใหม่อีกครั้ง',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'รหัสถูกต้อง',
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'กรุณากรอกรหัสผ่านใหม่อย่างน้อย 6 ตัวอักษร',
                'errors' => $validator->errors(),
            ], 422);
        }

        $record = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json([
                'status' => false,
                'message' => 'รหัสยืนยันไม่ถูกต้องหรือหมดอายุแล้ว กรุณากดขอรหัสใหม่อีกครั้ง',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้ในระบบ',
            ], 404);
        }

        // Update password with bcrypt hash
        $user->update([
            'password' => bcrypt($request->password),
        ]);

        // Delete used code
        PasswordResetCode::where('email', $request->email)->delete();

        return response()->json([
            'status' => true,
            'message' => 'เปลี่ยนรหัสผ่านสำเร็จแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่',
        ], 200);
    }
}
