<?php

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Single-use link letting someone create an account. Only the hash of its token is stored.
 */
#[Fillable(['token_hash', 'created_by', 'expires_at', 'accepted_at', 'user_id'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * Number of days an invitation stays valid.
     */
    public const VALIDITY_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Create an invitation and return it with its plain token, which is not stored.
     *
     * @return array{0: Invitation, 1: string}
     */
    public static function issue(User $admin): array
    {
        $token = Str::random(48);

        $invitation = static::create([
            'token_hash' => static::hash($token),
            'created_by' => $admin->id,
            'expires_at' => now()->addDays(self::VALIDITY_DAYS),
        ]);

        return [$invitation, $token];
    }

    /**
     * The pending invitation matching a plain token, if any.
     */
    public static function findPending(string $token): ?self
    {
        return static::query()->pending()->where('token_hash', static::hash($token))->first();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Invitations neither accepted nor expired.
     *
     * @param  Builder<Invitation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Account created with this invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
