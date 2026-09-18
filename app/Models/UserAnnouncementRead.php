<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAnnouncementRead extends Model
{
    protected $table = 'user_announcement_reads';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'announcement_id',
        'read_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class, 'announcement_id', 'announcement_id');
    }
}
