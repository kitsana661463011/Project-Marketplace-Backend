<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCategory extends Model
{
    protected $table = 'shop_category';
    protected $primaryKey = 'category_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'category_name',
        'description',
    ];

    public function shops()
    {
        return $this->hasMany(Shop::class, 'category_id', 'category_id');
    }
}
