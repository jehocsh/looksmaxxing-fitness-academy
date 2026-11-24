<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected $fillable = ['full_name', 'email', 'phone', 'address', 'latitude', 'longitude', 'membership_category_id', 'membership_level_id'];

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            $member->membership_no ??= 'LMX-'.Str::upper((string) Str::ulid());
        });
    }

    protected function casts(): array
    {
        return ['is_deactivated' => 'boolean', 'activated_at' => 'immutable_datetime', 'membership_expires_at' => 'immutable_datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class, 'membership_category_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MembershipTransaction::class);
    }

    public function membershipStatus(): string
    {
        if ($this->is_deactivated) {
            return 'Inactive';
        }
        if (! $this->membership_expires_at) {
            return 'Pending';
        }

        return $this->membership_expires_at->isFuture() ? 'Active' : 'Expired';
    }
}
