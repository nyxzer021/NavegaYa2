<?php

namespace App\Models;

// use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'document_number', 'google_id', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

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
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('status')->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('organization_id')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isCustomer(): bool
    {
        return $this->roles()->whereIn('code', ['customer', 'traveler', 'passenger'])->exists();
    }

    public function claimGuestPurchases(): void
    {
        if (! $this->hasVerifiedEmail()) {
            return;
        }
        Reservation::query()->whereNull('user_id')->whereRaw('LOWER(contact_email) = ?', [mb_strtolower($this->email)])->update(['user_id' => $this->id]);
        CheckoutOrder::query()->whereNull('user_id')->whereRaw('LOWER(contact_email) = ?', [mb_strtolower($this->email)])->update(['user_id' => $this->id]);
    }

    public function hasPermission(string $code): bool
    {
        if ($this->roles()->where('code', 'super_admin')->exists()) {
            return true;
        }
        if ($this->roles()->where('code', 'company_admin')->exists()) {
            return in_array($code, ['ports', 'routes', 'fleet', 'cargo', 'boarding', 'reports'], true);
        }
        if ($this->roles()->where('code', 'company_counter')->exists()) {
            return in_array($code, ['cargo', 'boarding'], true);
        }

        return $this->permissions()->where('code', $code)->exists();
    }
}
