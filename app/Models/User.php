<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'credits',
        'is_admin',
        'total_lines_processed',
        'current_month_usage',
        'usage_reset_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'credits' => 'integer',
            'total_lines_processed' => 'integer',
            'current_month_usage' => 'integer',
            'usage_reset_date' => 'date',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) ($this->is_admin ?? false);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function uploadLimits(): HasMany
    {
        return $this->hasMany(UserUploadLimit::class);
    }

    public function adminActions(): HasMany
    {
        return $this->hasMany(AdminActionLog::class, 'admin_user_id');
    }

    public function targetedAdminActions(): HasMany
    {
        return $this->hasMany(AdminActionLog::class, 'target_user_id');
    }

    public function uploadMetrics(): HasMany
    {
        return $this->hasMany(UploadMetric::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
