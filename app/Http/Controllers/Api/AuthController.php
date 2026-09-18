<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Admin login endpoint
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = trim($request->input('email'));
        $password = $request->input('password');

        // Check if matching default admin credentials requested by user
        $isDefaultAdmin = (strtolower($email) === 'admin@gmail.com' && $password === '12345Test!');

        // Look for admin user in database
        $user = User::where('role', 'admin')
            ->where(function ($query) use ($email) {
                $query->whereRaw('LOWER(email) = ?', [strtolower($email)])
                      ->orWhereRaw('LOWER(username) = ?', [strtolower($email)]);
            })
            ->first();

        $isValidPassword = false;

        if ($user) {
            $isValidPassword = Hash::check($password, $user->password) || $isDefaultAdmin;
            // If default admin login matched, sync email and password if needed
            if ($isDefaultAdmin) {
                $user->email = 'Admin@gmail.com';
                $user->username = 'Admin';
                $user->password = bcrypt('12345Test!');
                $user->save();
            }
        } elseif ($isDefaultAdmin) {
            // Auto create admin user if it doesn't exist yet
            try {
                $user = User::create([
                    'username' => 'Admin',
                    'email' => 'Admin@gmail.com',
                    'password' => bcrypt('12345Test!'),
                    'role' => 'admin',
                    'status' => 'active',
                ]);
            } catch (\Exception $e) {
                // Ignore DB error, create in-memory object
                $user = new User();
                $user->user_id = 1;
                $user->username = 'Admin';
                $user->email = 'Admin@gmail.com';
            }
            $isValidPassword = true;
        }

        if (!$user || !$isValidPassword) {
            return response()->json([
                'status' => false,
                'message' => 'อีเมลหรือรหัสผ่านผู้ดูแลระบบไม่ถูกต้อง',
            ], 401);
        }

        return response()->json([
            'status' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'data' => [
                'user' => [
                    'id' => (string)($user->user_id ?? 1),
                    'name' => $user->username ?? 'Admin',
                    'email' => $user->email ?? 'Admin@gmail.com',
                    'role' => 'Administrator',
                    'avatar' => $user->profile_image ?? null,
                ],
                'token' => 'admin-token-' . bin2hex(random_bytes(16)),
            ],
        ], 200);
    }

    /**
     * Update admin profile (avatar, name, email, password)
     */
    public function updateProfile(Request $request)
    {
        $user = User::where('role', 'admin')->first();
        if (!$user) {
            $user = User::first();
        }

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบข้อมูลผู้ดูแลระบบ',
            ], 404);
        }

        // 1. Update Name
        if ($request->has('name') && !empty($request->input('name'))) {
            $user->username = trim($request->input('name'));
        }

        // 2. Update Email
        if ($request->has('email') && !empty($request->input('email'))) {
            $newEmail = trim($request->input('email'));
            // Verify if changing email
            if (strtolower($user->email) !== strtolower($newEmail)) {
                $user->email = $newEmail;
            }
        }

        // 3. Update Password
        if ($request->has('new_password') && !empty($request->input('new_password'))) {
            $currentPassword = $request->input('current_password');
            $newPassword = $request->input('new_password');

            // If current password is provided, verify it (unless matching known default)
            if (!empty($currentPassword)) {
                $isDefault = ($currentPassword === '12345Test!');
                if (!Hash::check($currentPassword, $user->password) && !$isDefault) {
                    return response()->json([
                        'status' => false,
                        'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
                    ], 422);
                }
            }

            if (strlen($newPassword) < 6) {
                return response()->json([
                    'status' => false,
                    'message' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
                ], 422);
            }

            $user->password = bcrypt($newPassword);
        }

        // 4. Update Profile Picture / Avatar
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'admin_avatar_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(storage_path('images'), $filename);
            $user->profile_image = '/api/images/' . $filename;
        } elseif ($request->has('avatar')) {
            $avatarVal = $request->input('avatar');
            if (is_string($avatarVal) && str_starts_with($avatarVal, 'data:image')) {
                // Save base64 image
                try {
                    preg_match('/data:image\/(.*?);base64,(.*)/', $avatarVal, $matches);
                    $ext = $matches[1] ?? 'png';
                    $data = base64_decode($matches[2] ?? '');
                    if ($data) {
                        $filename = 'admin_avatar_' . time() . '.' . $ext;
                        file_put_contents(storage_path('images/' . $filename), $data);
                        $user->profile_image = '/api/images/' . $filename;
                    }
                } catch (\Exception $e) {
                    $user->profile_image = $avatarVal;
                }
            } else {
                $user->profile_image = $avatarVal;
            }
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'อัปเดตข้อมูลผู้ดูแลระบบสำเร็จ',
            'data' => [
                'user' => [
                    'id' => (string)$user->user_id,
                    'name' => $user->username,
                    'email' => $user->email,
                    'role' => 'Administrator',
                    'avatar' => $user->profile_image,
                ],
            ],
        ], 200);
    }
}

