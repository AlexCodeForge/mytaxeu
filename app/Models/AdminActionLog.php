<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_user_id',
        'action_type',
        'target_user_id',
        'target_ip_address',
        'old_value',
        'new_value',
        'notes',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
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
        string $actionType,
        array $data = []
    ): self {
        return static::create([
            'admin_user_id' => $adminUserId,
            'action_type' => $actionType,
            'target_user_id' => $data['target_user_id'] ?? null,
            'target_ip_address' => $data['target_ip_address'] ?? null,
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
