<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketPaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MarketPaymentSettingController extends Controller
{
    private function getBankNameByCode(?string $code): string
    {
        $banks = [
            'promptpay' => 'พร้อมเพย์ (PromptPay)',
            'kbank'     => 'ธนาคารกสิกรไทย (KBANK)',
            'scb'       => 'ธนาคารไทยพาณิชย์ (SCB)',
            'bbl'       => 'ธนาคารกรุงเทพ (BBL)',
            'ktb'       => 'ธนาคารกรุงไทย (KTB)',
            'bay'       => 'ธนาคารกรุงศรีอยุธยา (BAY)',
            'ttb'       => 'ธนาคารทหารไทยธนชาต (TTB)',
            'gsb'       => 'ธนาคารออมสิน (GSB)',
            'baac'      => 'ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (ธ.ก.ส.)',
            'other'     => 'บัญชีธนาคารอื่นๆ',
        ];

        return $banks[strtolower($code ?? '')] ?? ($code ?: 'พร้อมเพย์');
    }

    public function index()
    {
        $accounts = MarketPaymentSetting::orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        // If no records exist, provide empty or fallback
        $firstAccount = $accounts->where('is_active', true)->first() ?: $accounts->first();

        return response()->json([
            'status' => true,
            'message' => 'Payment settings retrieved successfully',
            'data' => [
                'id' => $firstAccount?->id,
                'bank_code' => $firstAccount?->bank_code ?? 'promptpay',
                'bank_name' => $firstAccount?->bank_name ?? 'พร้อมเพย์ (PromptPay)',
                'account_name' => $firstAccount?->account_name ?? '',
                'account_number' => $firstAccount?->account_number ?? '',
                'qr_code_path' => $firstAccount?->qr_code_path ?? null,
                'is_active' => $firstAccount?->is_active ?? true,
                'accounts' => $accounts,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        // Check if an existing ID was passed in POST for update
        if ($request->filled('id')) {
            return $this->update($request, $request->input('id'));
        }

        $validator = Validator::make($request->all(), [
            'bank_code'      => ['nullable', 'string', 'max:20'],
            'bank_name'      => ['nullable', 'string', 'max:100'],
            'account_name'   => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'qr_code'        => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'is_active'      => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'data'    => $validator->errors(),
            ], 422);
        }

        $bankCode = $request->input('bank_code', 'promptpay');
        $bankName = $request->input('bank_name') ?: $this->getBankNameByCode($bankCode);

        $account = new MarketPaymentSetting();
        $account->bank_code = $bankCode;
        $account->bank_name = $bankName;
        $account->account_name = $request->input('account_name');
        $account->account_number = $request->input('account_number');
        $account->is_active = $request->has('is_active') ? $request->boolean('is_active') : true;

        if ($request->hasFile('qr_code')) {
            $file = $request->file('qr_code');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'market_qrcode_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $account->qr_code_path = $filename;
        }

        $account->save();

        return response()->json([
            'status'  => true,
            'message' => 'เพิ่มบัญชีธนาคารรับเงินเรียบร้อยแล้ว',
            'data'    => $account->fresh(),
        ], 201);
    }

    public function update(Request $request, $id = null)
    {
        $targetId = $id ?: $request->input('id');

        if ($targetId) {
            $setting = MarketPaymentSetting::find($targetId);
        } else {
            $setting = MarketPaymentSetting::latest()->first();
        }

        if (! $setting) {
            $setting = new MarketPaymentSetting();
        }

        $validator = Validator::make($request->all(), [
            'bank_code'       => ['nullable', 'string', 'max:20'],
            'bank_name'       => ['nullable', 'string', 'max:100'],
            'account_name'    => ['required', 'string', 'max:100'],
            'account_number'  => ['required', 'string', 'max:50'],
            'qr_code'         => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'remove_qr_code'  => ['nullable'],
            'is_active'       => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'data'    => $validator->errors(),
            ], 422);
        }

        if ($request->boolean('remove_qr_code')) {
            if ($setting->qr_code_path) {
                Storage::disk('custom_images')->delete($setting->qr_code_path);
            }
            $setting->qr_code_path = null;
        }

        if ($request->hasFile('qr_code')) {
            if ($setting->qr_code_path) {
                Storage::disk('custom_images')->delete($setting->qr_code_path);
                if (file_exists(storage_path('images/' . $setting->qr_code_path))) {
                    @unlink(storage_path('images/' . $setting->qr_code_path));
                }
            }

            $file = $request->file('qr_code');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'market_qrcode_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');

            $setting->qr_code_path = $filename;
        }

        if ($request->filled('bank_code')) {
            $setting->bank_code = $request->input('bank_code');
            $setting->bank_name = $request->input('bank_name') ?: $this->getBankNameByCode($setting->bank_code);
        } elseif ($request->filled('bank_name')) {
            $setting->bank_name = $request->input('bank_name');
        }

        $setting->account_name = $request->input('account_name');
        $setting->account_number = $request->input('account_number');

        if ($request->has('is_active')) {
            $setting->is_active = $request->boolean('is_active');
        }

        $setting->save();

        return response()->json([
            'status'  => true,
            'message' => 'บันทึกข้อมูลบัญชีธนาคารเรียบร้อยแล้ว',
            'data'    => $setting->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $setting = MarketPaymentSetting::find($id);

        if (! $setting) {
            return response()->json([
                'status'  => false,
                'message' => 'ไม่พบบัญชีธนาคารนี้ในระบบ',
            ], 404);
        }

        if ($setting->qr_code_path) {
            Storage::disk('custom_images')->delete($setting->qr_code_path);
        }

        $setting->delete();

        return response()->json([
            'status'  => true,
            'message' => 'ลบบัญชีธนาคารสำเร็จแล้ว',
        ], 200);
    }

    public function toggleActive($id)
    {
        $setting = MarketPaymentSetting::find($id);

        if (! $setting) {
            return response()->json([
                'status'  => false,
                'message' => 'ไม่พบบัญชีธนาคารนี้ในระบบ',
            ], 404);
        }

        $setting->is_active = !$setting->is_active;
        $setting->save();

        return response()->json([
            'status'  => true,
            'message' => ($setting->is_active ? 'เปิด' : 'ปิด') . 'การใช้งานบัญชีสำเร็จแล้ว',
            'data'    => $setting->fresh(),
        ], 200);
    }
}
