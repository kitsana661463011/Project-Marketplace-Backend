<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentHistory extends Model
{
    protected $table = 'payment_history';
    protected $primaryKey = 'history_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'payment_id',
        'amount',
        'payment_date',
        'verified_date',
        'payment_method',
        'status',
        'remark',
    ];

    public function booking()
    {
        return $this->belongsTo(StallBooking::class, 'booking_id', 'booking_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }
}
