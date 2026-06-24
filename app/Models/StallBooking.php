<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StallBooking extends Model
{
    protected $table = 'stall_booking';
    protected $primaryKey = 'booking_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'stall_id',
        'booking_date',
        'start_date',
        'end_date',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function stall()
    {
        return $this->belongsTo(Stall::class, 'stall_id', 'stall_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'booking_id', 'booking_id');
    }
}
