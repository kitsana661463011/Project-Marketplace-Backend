<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowShop extends Model
{
    protected $table = 'follow_shop';
    protected $primaryKey = 'follow_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'shop_id',
        'follow_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }
}
