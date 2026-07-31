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
            'status' => ['nullable', Rule::in(['active', 'inactive', 'banned'])],
            'citizen_id' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'document_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'request_more'])],
            'submission_date' => ['nullable', 'date'],
            'document_image' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
            'profile_image' => $request->profile_image,
            'role' => $request->role,
            'status' => $request->status ?? 'active',
            'citizen_id' => $request->citizen_id,
            'address' => $request->address,
            'document_status' => $request->document_status ?? 'pending',
            'submission_date' => $request->submission_date,
            'document_image' => $request->document_image,
        ]);

        if ($request->has('interests') && !empty($request->interests)) {
            $interestsRaw = $request->interests;
            $interestNames = is_array($interestsRaw)
                ? $interestsRaw
                : array_map('trim', explode(',', (string) $interestsRaw));

            foreach ($interestNames as $name) {
                if (empty($name)) continue;
                $opt = \Illuminate\Support\Facades\DB::table('user_interest_option')
                    ->where('interest_name', $name)
                    ->first();
                if ($opt) {
                    \Illuminate\Support\Facades\DB::table('user_has_interest')->insertOrIgnore([
                        'user_id' => $user->user_id,
                        'interest_id' => $opt->interest_id,
                    ]);
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => $user,
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
            ->pluck('uio.interest_name')
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully',
            'data' => $arr,
        ], 200);
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
            'email' => ['sometimes', 'email', 'max:100', Rule::unique('user', 'email')->ignore($user->user_id, 'user_id')],
            'password' => ['sometimes', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:15'],
            'profile_image' => ['nullable', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(['buyer', 'seller', 'admin'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'banned'])],
            'citizen_id' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'interests' => ['nullable'],
            'document_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'request_more'])],
            'submission_date' => ['nullable', 'date'],
            'document_image' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['username', 'email', 'phone', 'profile_image', 'role', 'status', 'citizen_id', 'address', 'document_status', 'submission_date', 'document_image']);

        $userInterests = null;
        if ($request->has('interests')) {
            $userInterests = [];
            $interestsRaw = $request->input('interests');
            $interestNames = is_array($interestsRaw)
                ? $interestsRaw
                : array_map('trim', explode(',', (string) $interestsRaw));

            \Illuminate\Support\Facades\DB::table('user_has_interest')
                ->where('user_id', $user->user_id)
                ->delete();

            foreach ($interestNames as $name) {
                if (empty($name)) continue;
                $userInterests[] = $name;
                $opt = \Illuminate\Support\Facades\DB::table('user_interest_option')
                    ->where('interest_name', $name)
                    ->first();
                if ($opt) {
                    \Illuminate\Support\Facades\DB::table('user_has_interest')->insertOrIgnore([
                        'user_id' => $user->user_id,
                        'interest_id' => $opt->interest_id,
                    ]);
                }
            }
        }

        if ($request->hasFile('profile_image_file')) {
            $file = $request->file('profile_image_file');
            $filename = time() . '_profile_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(storage_path('images'), $filename);
            $data['profile_image'] = $filename;
        } elseif ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = time() . '_profile_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(storage_path('images'), $filename);
            $data['profile_image'] = $filename;
        }

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        $freshUser = $user->fresh()->toArray();
        if ($userInterests !== null) {
            $freshUser['interests'] = $userInterests;
        } else {
            $freshUser['interests'] = \Illuminate\Support\Facades\DB::table('user_has_interest as uhi')
                ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
                ->where('uhi.user_id', $user->user_id)
                ->pluck('uio.interest_name')
                ->toArray();
        }

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

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully',
            'data' => null,
        ], 200);
    }
}
