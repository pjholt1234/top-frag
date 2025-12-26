<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'discord_id',
        'discord_link_hash',
        'steam_sharecode',
        'steam_game_auth_code',
        'steam_sharecode_added_at',
        'steam_match_processing_enabled',
        'steam_last_processed_at',
        'steam_persona_name',
        'steam_profile_url',
        'steam_avatar',
        'steam_avatar_medium',
        'steam_avatar_full',
        'steam_persona_state',
        'steam_community_visibility_state',
        'steam_profile_updated_at',
        'faceit_player_id',
        'faceit_nickname',
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
            'steam_sharecode_added_at' => 'datetime',
            'steam_match_processing_enabled' => 'boolean',
            'steam_last_processed_at' => 'datetime',
            'steam_profile_updated_at' => 'datetime',
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

    public function ownedClans(): HasMany
    {
        return $this->hasMany(Clan::class, 'owned_by');
    }

    public function clanMemberships(): HasMany
    {
        return $this->hasMany(ClanMember::class);
    }

    public function clans(): BelongsToMany
    {
        return $this->belongsToMany(Clan::class, 'clan_members', 'user_id', 'clan_id')
            ->withTimestamps();
    }

    public function clanLeaderboards(): HasMany
    {
        return $this->hasMany(ClanLeaderboard::class);
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

    /**
     * Get the Discord link hash for this user
     */
    public function getDiscordLinkHash(): string
    {
        // The observer should have already created this, but just in case
        if (! $this->discord_link_hash) {
            $this->discord_link_hash = hash('sha256', $this->id.config('app.key').time().uniqid());
            $this->save();
        }

        return $this->discord_link_hash;
    }

    /**
     * Check if user has a Steam sharecode configured
     */
    public function hasSteamSharecode(): bool
    {
        return ! empty($this->steam_sharecode);
    }

    /**
     * Validate Steam sharecode format
     */
    public static function isValidSharecode(string $sharecode): bool
    {
        // Steam sharecode format: CSGO-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX (case insensitive)
        $pattern = '/^CSGO-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}$/';

        return preg_match($pattern, $sharecode) === 1;
    }

    /**
     * Validate Steam game authentication code format
     */
    public static function isValidGameAuthCode(string $authCode): bool
    {
        // Steam game auth code format: AAAA-AAAAA-AAAA (example format)
        // The actual format may vary, but typically contains alphanumeric characters and hyphens
        $pattern = '/^[A-Z0-9]{4}-[A-Z0-9]{5}-[A-Z0-9]{4}$/';

        return preg_match($pattern, $authCode) === 1;
    }

    /**
     * Check if user has both required Steam codes for match processing
     */
    public function hasCompleteSteamSetup(): bool
    {
        return ! empty($this->steam_sharecode) && ! empty($this->steam_game_auth_code);
    }
}
