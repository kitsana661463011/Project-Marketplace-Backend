<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMap extends Model
{
    protected $table = 'market_maps';
    protected $primaryKey = 'map_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'map_name',
        'map_width',
        'map_height',
    ];

    public function items()
    {
        return $this->hasMany(MarketMapItem::class, 'map_id', 'map_id');
    }
}
