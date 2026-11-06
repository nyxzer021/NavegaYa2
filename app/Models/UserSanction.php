<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSanction extends Model
{
    protected $fillable = ['user_id', 'kind', 'reason', 'ends_at', 'created_by', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function activeBlock(User $user): ?self
    {
        return self::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->where('kind', 'ban')
                    ->orWhere(function ($suspension) {
                        $suspension->where('kind', 'suspension')
                            ->where(function ($window) {
                                $window->whereNull('ends_at')->orWhere('ends_at', '>', now());
                            });
                    });
            })
            ->latest('id')
            ->first();
    }

    public static function activeWarning(User $user): ?self
    {
        if (self::activeBlock($user)) {
            return null;
        }

        return self::query()
            ->where('user_id', $user->id)
            ->where('kind', 'warning')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    public function blocksParticipation(): bool
    {
        return in_array($this->kind, ['ban', 'suspension'], true);
    }

    public function loginMessage(): string
    {
        if ($this->kind === 'ban') {
            return 'Tu cuenta está bloqueada de forma permanente. Motivo: '.$this->reason;
        }

        $until = $this->ends_at ? ' hasta el '.$this->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i') : '';

        return 'Tu cuenta está suspendida'.$until.'. Motivo: '.$this->reason;
    }
}
