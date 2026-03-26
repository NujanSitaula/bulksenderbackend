<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailRecipient extends Model
{
    protected $fillable = [
        'email_batch_id',
        'email',
        'unsubscribe_token',
        'open_token',
        'unsubscribed_at',
        'opened_at',
        'send_status',
        'send_error',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(EmailBatch::class, 'email_batch_id');
    }
}
