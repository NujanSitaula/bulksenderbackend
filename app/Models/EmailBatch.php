<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailBatch extends Model
{
    protected $fillable = [
        'created_by',
        'subject_plain',
        'subject_html',
        'body_html',
        'status',
    ];

    public function recipients()
    {
        return $this->hasMany(EmailRecipient::class, 'email_batch_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
