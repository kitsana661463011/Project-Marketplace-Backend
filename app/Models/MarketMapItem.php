<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMapItem extends Model
{
    protected $table = 'market_map_items';
    protected $primaryKey = 'map_item_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'map_id',
        'item_type',
        'stall_id',
        'zone_id',
        'label',
        'x',
        'y',
        'width',
        'height',
        'fill_color',
        'rotation',
        'z_index',
    ];

    public function map()
    {
        return $this->belongsTo(MarketMap::class, 'map_id', 'map_id');
    }

    public function stall()
    {
        return $this->belongsTo(Stall::class, 'stall_id', 'stall_id');
    }

    public function zone()
    {
        return $this->belongsTo(MarketZone::class, 'zone_id', 'zone_id');
    }
}
