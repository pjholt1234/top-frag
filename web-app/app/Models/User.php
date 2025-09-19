<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'steam_id',
        'steam_link_hash',
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
        ];
    }

    public function player(): HasOne
    {
        return $this->hasOne(Player::class, 'steam_id', 'steam_id');
    }

    public function matches(): Collection
    {
        return $this->player?->matches ?? collect();
    }

    public function demoProcessingJobs(): HasMany
    {
        return $this->hasMany(DemoProcessingJob::class, 'user_id');
    }

    public function grenadeFavourites(): HasMany
    {
        return $this->hasMany(GrenadeFavourite::class, 'user_id');
    }

    public function uploadedGames(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'uploaded_by');
    }

    /**
     * Get the Steam link hash for this user
     */
    public function getSteamLinkHash(): string
    {
        // The observer should have already created this, but just in case
        if (! $this->steam_link_hash) {
            $this->steam_link_hash = hash('sha256', $this->id.config('app.key').time().uniqid());
            $this->save();
        }

        return $this->steam_link_hash;
    }
}
