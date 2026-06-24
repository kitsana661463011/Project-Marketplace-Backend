<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReport extends Model
{
    protected $table = 'review_report';
    protected $primaryKey = 'report_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'user_id',
        'report_count',
        'report_reason',
        'report_date',
        'report_status',
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
