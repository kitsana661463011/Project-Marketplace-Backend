<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketZone extends Model
{
    protected $table = 'market_zone';
    protected $primaryKey = 'zone_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'zone_name',
        'zone_price',
    ];

    public function stalls()
    {
        return $this->hasMany(Stall::class, 'zone_id', 'zone_id');
    }

    public function mapItems()
    {
        return $this->hasMany(MarketMapItem::class, 'zone_id', 'zone_id');
    }
}
