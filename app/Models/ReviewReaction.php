<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReaction extends Model
{
    protected $table = 'review_reaction';
    protected $primaryKey = 'reaction_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'review_id',
        'user_id',
        'reaction_type',
    ];

    public function review()
    {
        return $this->belongsTo(ShopReview::class, 'review_id', 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
