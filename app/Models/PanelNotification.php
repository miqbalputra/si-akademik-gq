<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelNotification extends Model
{
    protected $fillable = [
        'user_id',
        'audience_role',
        'title',
        'body',
        'severity',
        'notification_type',
        'link_url',
        'status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->status === 'unread') {
            $this->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }
    }

    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }
}