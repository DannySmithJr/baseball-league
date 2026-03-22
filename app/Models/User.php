<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'twitch_username'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->id === 1) {
                throw new \RuntimeException('The founding admin account cannot be deleted.');
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGm(): bool
    {
        return \DB::table('gms')->where('user_id', $this->id)->where('status', 2)->exists();
    }

    public function gmRecord(): ?object
    {
        return \DB::table('gms as gm')
            ->join('teams as t', 't.team_id', '=', 'gm.team_id')
            ->where('gm.user_id', $this->id)
            ->where('gm.status', 2)
            ->first();
    }

    public function hasPendingGm(): bool
    {
        return \DB::table('gms')->where('user_id', $this->id)->where('status', 1)->exists();
    }

    public function hasCcp(): bool
    {
        return \DB::table('ccps')->where('user_id', $this->id)->exists();
    }

    public function ccp(): ?object
    {
        return \DB::table('ccps')->where('user_id', $this->id)->first();
    }

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
}
