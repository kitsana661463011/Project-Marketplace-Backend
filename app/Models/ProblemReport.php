<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProblemReport extends Model
{
    protected $table = 'problem_report';
    protected $primaryKey = 'problem_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'stall_id',
        'description',
        'image',
        'report_date',
        'status',
        'admin_comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function stall()
    {
        return $this->belongsTo(Stall::class, 'stall_id', 'stall_id');
    }
}
