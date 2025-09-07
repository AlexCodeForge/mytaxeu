<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpUploadTracking extends Model
{
    use HasFactory;

    protected $table = 'ip_upload_tracking';

    protected $fillable = [
        'ip_address',
        'upload_count',
        'total_lines_attempted',
        'last_upload_at',
    ];

    protected $casts = [
        'upload_count' => 'integer',
        'total_lines_attempted' => 'integer',
        'last_upload_at' => 'datetime',
    ];

    public function incrementUsage(int $lineCount): void
    {
        $this->increment('upload_count');
        $this->increment('total_lines_attempted', $lineCount);
        $this->update(['last_upload_at' => now()]);
    }

    public static function findOrCreateForIp(string $ipAddress): self
    {
        return static::firstOrCreate(
            ['ip_address' => $ipAddress],
            [
                'upload_count' => 0,
                'total_lines_attempted' => 0,
                'last_upload_at' => now(),
            ]
        );
    }
}
