<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payment';
    protected $primaryKey = 'payment_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'amount',
        'payment_date',
        'payment_slip',
        'status',
        'refund_reason',
        'refund_bank_name',
        'refund_account_number',
        'refund_account_name',
        'refund_slip',
        'refunded_at',
        'remark',
    ];

    public function booking()
    {
        return $this->belongsTo(StallBooking::class, 'booking_id', 'booking_id');
    }
}
