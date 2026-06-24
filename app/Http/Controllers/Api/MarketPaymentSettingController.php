<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketPaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MarketPaymentSettingController extends Controller
{
    public function index()
    {
        $setting = MarketPaymentSetting::latest()->first();

        return response()->json([
            'status' => true,
            'message' => 'Payment settings retrieved successfully',
            'data' => $setting,
        ], 200);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'qr_code' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'remove_qr_code' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $setting = MarketPaymentSetting::latest()->first();

        if (! $setting) {
            $setting = new MarketPaymentSetting();
        }

        if ($request->boolean('remove_qr_code')) {
            if ($setting->qr_code_path && Storage::disk('public')->exists($setting->qr_code_path)) {
                Storage::disk('public')->delete($setting->qr_code_path);
            }
            $setting->qr_code_path = null;
        }

        if ($request->hasFile('qr_code')) {
            if ($setting->qr_code_path && Storage::disk('public')->exists($setting->qr_code_path)) {
                Storage::disk('public')->delete($setting->qr_code_path);
            }

            $path = $this->storeUploadedFile($request->file('qr_code'));
            $setting->qr_code_path = $path;
        }

        $setting->account_name = $request->input('account_name');
        $setting->account_number = $request->input('account_number');
        $setting->save();

        return response()->json([
            'status' => true,
            'message' => 'Payment settings updated successfully',
            'data' => $setting->fresh(),
        ], 200);
    }

    protected function storeUploadedFile(UploadedFile $file): string
    {
        $relativePath = $file->hashName('qr-codes');
        $relativePath = str_replace('\\', '/', $relativePath);

        $absoluteDirectory = dirname(storage_path('app/public/' . $relativePath));

        if (! is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0755, true);
        }

        $file->move($absoluteDirectory, basename($relativePath));

        return $relativePath;
    }
}
