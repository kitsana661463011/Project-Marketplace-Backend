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
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:user,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:15'],
            'profile_image' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['buyer', 'seller', 'admin'])],
            'interests' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['username', 'email', 'phone', 'profile_image', 'role']);
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

        $data = $request->only(['username', 'phone', 'role', 'status', 'citizen_id', 'document_status', 'submission_date', 'address']);

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
}
