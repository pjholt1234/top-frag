<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Clan extends Model
{
    use HasFactory;

    protected $fillable = [
        'owned_by',
        'invite_link',
        'name',
        'tag',
        'discord_guild_id',
        'discord_channel_id',
    ];

    protected $casts = [
        'invite_link' => 'string',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClanMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clan_members', 'clan_id', 'user_id')
            ->withTimestamps();
    }

    public function matches(): BelongsToMany
    {
        return $this->belongsToMany(GameMatch::class, 'clan_matches', 'clan_id', 'match_id')
            ->withTimestamps();
    }

    public function leaderboards(): HasMany
    {
        return $this->hasMany(ClanLeaderboard::class);
    }

    // Methods
    public function generateInviteLink(): string
    {
        $this->invite_link = (string) Str::uuid();
        $this->save();

        return $this->invite_link;
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->owned_by === (int) $user->id;
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public static function findByDiscordGuildId(string $guildId): ?self
    {
        return self::where('discord_guild_id', $guildId)->first();
    }

    public function isLinkedToDiscord(): bool
    {
        return $this->discord_guild_id !== null;
    }
}
