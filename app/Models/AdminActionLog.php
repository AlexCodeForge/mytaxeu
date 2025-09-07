<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'action',
        'target_user_id',
        'target_upload_id',
        'details',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function logAction(
        int $adminUserId,
        string $action,
        array $data = []
    ): self {
        return static::create([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'target_user_id' => $data['target_user_id'] ?? null,
            'target_upload_id' => $data['target_upload_id'] ?? null,
            'details' => $data['details'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);
    }
}
