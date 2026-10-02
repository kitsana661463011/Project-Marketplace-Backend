<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all()->map(function ($u) {
            $arr = $u->toArray();
            $arr['interests'] = \Illuminate\Support\Facades\DB::table('user_has_interest as uhi')
                ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
                ->where('uhi.user_id', $u->user_id)
                ->orderBy('uhi.sort_order', 'asc')
                ->pluck('uio.interest_name')
                ->toArray();
            return $arr;
        });

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ], 200);
    }

    public function store(Request $request)
    {
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:user,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:15'],
            'profile_image' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['buyer', 'seller', 'admin'])],
            'interests' => ['nullable'],
        ], [
            'email.unique' => 'อีเมลนี้มีผู้ใช้งานแล้วในระบบ กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.required' => 'กรุณากรอกอีเมล',
            'username.required' => 'กรุณากรอกชื่อ-นามสกุล',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
            'password.min' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first() ?: 'ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['username', 'email', 'phone', 'profile_image', 'role']);
        $data['email'] = strtolower(trim($data['email']));
        $data['password'] = bcrypt($request->password);

        $user = User::create($data);

        if ($request->has('interests') && !empty($request->interests)) {
            $interestsRaw = $request->interests;
            $interestNames = is_array($interestsRaw)
                ? $interestsRaw
                : array_map('trim', explode(',', (string) $interestsRaw));

            $order = 1;
            foreach ($interestNames as $name) {
                if (empty($name)) continue;
                $opt = \Illuminate\Support\Facades\DB::table('user_interest_option')
                    ->where('interest_name', $name)
                    ->first();
                if ($opt) {
                    \Illuminate\Support\Facades\DB::table('user_has_interest')->insertOrIgnore([
                        'user_id' => $user->user_id,
                        'interest_id' => $opt->interest_id,
                        'sort_order' => $order++,
                    ]);
                }
            }
        }

        $freshUser = $user->fresh()->toArray();
        $freshUser['interests'] = \Illuminate\Support\Facades\DB::table('user_has_interest as uhi')
            ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
            ->where('uhi.user_id', $user->user_id)
            ->orderBy('uhi.sort_order', 'asc')
            ->pluck('uio.interest_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => $freshUser,
        ], 201);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        $arr = $user->toArray();
        $arr['interests'] = \Illuminate\Support\Facades\DB::table('user_has_interest as uhi')
            ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
            ->where('uhi.user_id', $user->user_id)
            ->orderBy('uhi.sort_order', 'asc')
            ->pluck('uio.interest_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully',
            'data' => $arr,
        ], 200);
    }

    private function uploadProfileImage($file, $oldImage = null)
    {
        if (!file_exists(storage_path('images'))) {
            @mkdir(storage_path('images'), 0777, true);
        }

        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = 'user_profile_' . time() . '_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    private function uploadDocumentImage($file, $oldImage = null)
    {
        if (!file_exists(storage_path('images'))) {
            @mkdir(storage_path('images'), 0777, true);
        }

        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = 'seller_doc_' . time() . '_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:100', Rule::unique('user')->ignore($user->user_id, 'user_id')],
            'password' => ['sometimes', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:15'],
            'profile_image' => ['nullable'],
            'profile_image_file' => ['nullable'],
            'role' => ['sometimes', Rule::in(['buyer', 'seller', 'admin'])],
            'status' => ['nullable', 'string'],
            'citizen_id' => ['nullable', 'string', 'max:20'],
            'document_status' => ['nullable', 'string'],
            'submission_date' => ['nullable', 'string'],
            'document_image' => ['nullable'],
            'document_image_file' => ['nullable'],
            'address' => ['nullable', 'string'],
            'interests' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        // Security Guard: Only allow user-modifiable profile fields
        // 'role', 'status', and 'document_status' must NOT be freely settable by the client.
        $data = $request->only(['username', 'phone', 'address']);

        // Handle vendor application / document submission
        $hasDocImage = $request->hasFile('document_image_file') || $request->hasFile('document_image') || $request->filled('document_image');
        $hasCitizenId = $request->filled('citizen_id');

        if ($hasCitizenId) {
            $cleaned = preg_replace('/\D/', '', (string) $request->input('citizen_id'));
            if (!empty($cleaned)) {
                $data['citizen_id'] = $cleaned;
            }
        }

        // When a non-seller submits vendor documents or reapplies:
        // Always enforce role = 'buyer', document_status = 'pending', and record submission_date.
        // Role = 'seller' and document_status = 'approved' CAN ONLY be granted by admin approval endpoint.
        if ($hasDocImage || $hasCitizenId) {
            if ($user->role !== 'seller') {
                $data['role'] = 'buyer';
                $data['document_status'] = 'pending';
                $data['submission_date'] = now();
                $data['reject_reason'] = null; // Clear rejection reason on new submission
            }
        }

        if ($request->filled('email')) {
            $data['email'] = strtolower(trim($request->email));
        }

        if ($request->has('interests')) {
            $interestsRaw = $request->input('interests');
            $interestNames = is_array($interestsRaw)
                ? $interestsRaw
                : array_map('trim', explode(',', (string) $interestsRaw));

            \Illuminate\Support\Facades\DB::table('user_has_interest')
                ->where('user_id', $user->user_id)
                ->delete();

            $order = 1;
            foreach ($interestNames as $name) {
                if (empty($name)) continue;
                $opt = \Illuminate\Support\Facades\DB::table('user_interest_option')
                    ->where('interest_name', $name)
                    ->first();
                if ($opt) {
                    \Illuminate\Support\Facades\DB::table('user_has_interest')->insertOrIgnore([
                        'user_id' => $user->user_id,
                        'interest_id' => $opt->interest_id,
                        'sort_order' => $order++,
                    ]);
                }
            }
        }

        if ($request->hasFile('profile_image_file')) {
            $data['profile_image'] = $this->uploadProfileImage($request->file('profile_image_file'), $user->profile_image);
        } elseif ($request->hasFile('profile_image')) {
            $data['profile_image'] = $this->uploadProfileImage($request->file('profile_image'), $user->profile_image);
        }

        if ($request->hasFile('document_image_file')) {
            $data['document_image'] = $this->uploadDocumentImage($request->file('document_image_file'), $user->document_image);
        } elseif ($request->hasFile('document_image')) {
            $data['document_image'] = $this->uploadDocumentImage($request->file('document_image'), $user->document_image);
        } elseif ($request->filled('document_image') && is_string($request->input('document_image'))) {
            $data['document_image'] = $request->input('document_image');
        }

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        $freshUser = $user->fresh()->toArray();
        $freshUser['interests'] = \Illuminate\Support\Facades\DB::table('user_has_interest as uhi')
            ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
            ->where('uhi.user_id', $user->user_id)
            ->orderBy('uhi.sort_order', 'asc')
            ->pluck('uio.interest_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'data' => $freshUser,
        ], 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        if ($user->profile_image && file_exists(storage_path('images/' . $user->profile_image))) {
            @unlink(storage_path('images/' . $user->profile_image));
        }

        if ($user->document_image && file_exists(storage_path('images/' . $user->document_image))) {
            @unlink(storage_path('images/' . $user->document_image));
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully',
            'data' => null,
        ], 200);
    }

    /**
     * Cancel / Delete a submitted vendor application
     */
    public function cancelVendorApplication($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        // Cannot cancel if already an approved seller
        if ($user->role === 'seller' && $user->document_status === 'approved') {
            return response()->json([
                'status' => false,
                'message' => 'ไม่สามารถยกเลิกคำขอได้ เนื่องจากบัญชีนี้ได้รับการอนุมัติเป็นผู้ค้าแล้ว',
                'data' => null,
            ], 400);
        }

        // Remove old document image file from disk if stored locally
        if ($user->document_image && file_exists(storage_path('images/' . $user->document_image))) {
            @unlink(storage_path('images/' . $user->document_image));
        }

        $user->document_status = 'pending';
        $user->submission_date = null;
        $user->citizen_id = null;
        $user->document_image = null;
        $user->reject_reason = null;
        $user->save();

        $freshUser = $user->fresh()->toArray();
        $freshUser['interests'] = \Illuminate\Support\Facades\DB::table('user_has_interest as uhi')
            ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
            ->where('uhi.user_id', $user->user_id)
            ->orderBy('uhi.sort_order', 'asc')
            ->pluck('uio.interest_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'ยกเลิกคำขอสมัครเป็นผู้ค้าเรียบร้อยแล้ว',
            'data' => $freshUser,
        ], 200);
    }
}
