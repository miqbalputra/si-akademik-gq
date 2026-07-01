<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExportRequest extends Model
{
    protected $fillable = [
        'requested_by',
        'export_type',
        'panel_key',
        'filters',
        'status',
        'file_path',
        'error_message',
        'requested_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $filePath): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'finished_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'finished_at' => now(),
        ]);
    }
}