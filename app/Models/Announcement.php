<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'announcement';
    protected $primaryKey = 'announcement_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'announcement_type',
        'description',
        'image',
        'publish_date',
        'end_date',
        'status',
        'user_id',
    ];

    protected $casts = [
        'publish_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function scopeActiveRange($query)
    {
        $now = now();
        return $query->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('publish_date')->orWhere('publish_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')->where('end_date', '<', now());
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('publish_date')->where('publish_date', '>', now());
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
