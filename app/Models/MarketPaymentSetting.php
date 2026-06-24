<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketPaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name',
        'account_number',
        'qr_code_path',
    ];
}
