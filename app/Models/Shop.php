<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $table = 'shop';
    protected $primaryKey = 'shop_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'shop_name',
        'category_id',
        'description',
        'shop_phone',
        'social_links',
        'shop_image',
        'user_id',
    ];

    public function category()
    {
        return $this->belongsTo(ShopCategory::class, 'category_id', 'category_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'shop_id', 'shop_id');
    }

    public function reviews()
    {
        return $this->hasMany(ShopReview::class, 'shop_id', 'shop_id');
    }

    public function follows()
    {
        return $this->hasMany(FollowShop::class, 'shop_id', 'shop_id');
    }
}
