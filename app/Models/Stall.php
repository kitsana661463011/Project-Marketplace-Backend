<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stall extends Model
{
    protected $table = 'stall';
    protected $primaryKey = 'stall_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'stall_number',
        'size',
        'price',
        'rental_type',
        'daily_price',
        'monthly_price',
        'entry_fee',
        'security_deposit',
        'has_electricity',
        'has_water',
        'image1',
        'image2',
        'status',
        'zone_id',
    ];

    protected $appends = [
        'images',
    ];

    public function getImagesAttribute(): array
    {
        return array_values(array_filter([$this->image1, $this->image2]));
    }

    protected $casts = [
        'has_electricity' => 'boolean',
        'has_water' => 'boolean',
        'daily_price' => 'float',
        'monthly_price' => 'float',
        'entry_fee' => 'float',
        'security_deposit' => 'float',
        'price' => 'float',
    ];

    public function zone()
    {
        return $this->belongsTo(MarketZone::class, 'zone_id', 'zone_id');
    }

    public function bookings()
    {
        return $this->hasMany(StallBooking::class, 'stall_id', 'stall_id');
    }

    public function problemReports()
    {
        return $this->hasMany(ProblemReport::class, 'stall_id', 'stall_id');
    }

    public function mapItems()
    {
        return $this->hasMany(MarketMapItem::class, 'stall_id', 'stall_id');
    }
}
