<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketPaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_code',
        'bank_name',
        'account_name',
        'account_number',
        'qr_code_path',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];
}
