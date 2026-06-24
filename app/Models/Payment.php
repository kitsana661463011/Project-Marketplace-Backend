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
    ];

    public function booking()
    {
        return $this->belongsTo(StallBooking::class, 'booking_id', 'booking_id');
    }
}
