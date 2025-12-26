<?php

namespace App\Http\Controllers\Api;

use App\Actions\FetchAndStoreFaceITProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeEmailRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ChangeUsernameRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\Integrations\Steam\SteamAPIConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private readonly SteamAPIConnector $steamApiConnector,
        private readonly FetchAndStoreFaceITProfileAction $fetchAndStoreFaceITProfileAction
    ) {}

    /**
     * Fetch and store Steam profile data for a user
     */
    private function fetchAndStoreSteamProfile(User $user): void
    {
        if (! $user->steam_id) {
            return;
        }

        try {
            $steamProfiles = $this->steamApiConnector->getPlayerSummaries([$user->steam_id]);

            if ($steamProfiles && isset($steamProfiles[$user->steam_id])) {
                $profile = $steamProfiles[$user->steam_id];

                $user->update([
                    'steam_persona_name' => $profile['persona_name'],
                    'steam_profile_url' => $profile['profile_url'],
                    'steam_avatar' => $profile['avatar'],
                    'steam_avatar_medium' => $profile['avatar_medium'],
                    'steam_avatar_full' => $profile['avatar_full'],
                    'steam_persona_state' => $profile['persona_state'],
                    'steam_community_visibility_state' => $profile['community_visibility_state'],
                    'steam_profile_updated_at' => now(),
                ]);

                Log::info('Updated Steam profile for user', [
                    'user_id' => $user->id,
                    'steam_id' => $user->steam_id,
                    'persona_name' => $profile['persona_name'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Steam profile for user', [
                'user_id' => $user->id,
                'steam_id' => $user->steam_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => config('messaging.registered') ?? 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [config('messaging.generic.critical-error')],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => config('messaging.auth.login') ?? 'User login successfully',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => config('messaging.auth.logout') ?? 'User logout successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Redirect to Steam for authentication
     */
    public function steamRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('steam')->redirect();
    }

    /**
     * Handle Steam authentication callback
     */
    public function steamCallback(): \Illuminate\Http\RedirectResponse
    {
        try {
            $steamUser = Socialite::driver('steam')->user();
            Log::info('Steam user ID: '.$steamUser->getId());

            // Check if user already exists with this Steam ID
            $user = User::where('steam_id', $steamUser->getId())->first();

            if ($user) {
                // User exists, update their Steam profile and log them in
                $this->fetchAndStoreSteamProfile($user);
                $this->fetchAndStoreFaceITProfileAction->execute($user);

                $token = $user->createToken('auth_token')->plainTextToken;

                // Redirect to frontend with token
                return redirect(config('app.frontend_url').'/steam-callback?token='.$token.'&success=true&message='.urlencode('Steam login successful'));
            }

            // Check if this is a Steam account linking request
            $linkHash = request('link');
            Log::info('Steam callback - link hash: '.($linkHash ?? 'null'));

            if ($linkHash) {
                $currentUser = User::where('steam_link_hash', $linkHash)->first();
                Log::info('Steam linking detected for user: '.($currentUser ? $currentUser->id : 'not found'));

                if (! $currentUser) {
                    return redirect(config('app.frontend_url').'/steam-callback?error=user_not_found&message='.urlencode('User not found'));
                }

                // Check if another user already has this Steam ID
                if (User::where('steam_id', $steamUser->getId())->exists()) {
                    return redirect(config('app.frontend_url').'/steam-callback?error=steam_already_linked&message='.urlencode('This Steam account is already linked to another user'));
                }

                // Link Steam account to current user
                $currentUser->update(['steam_id' => $steamUser->getId()]);
                Log::info('Successfully linked Steam ID '.$steamUser->getId().' to user ID '.$currentUser->id);

                // Fetch and store Steam profile data
                $this->fetchAndStoreSteamProfile($currentUser);
                $this->fetchAndStoreFaceITProfileAction->execute($currentUser);

                // Redirect to frontend with success
                return redirect(config('app.frontend_url').'/steam-callback?success=true&message='.urlencode('Steam account linked successfully'));
            }

            // Create new user with Steam account
            Log::info('Creating new Steam user with ID: '.$steamUser->getId());
            $user = User::create([
                'name' => $steamUser->getNickname() ?? 'Steam User',
                'email' => $steamUser->getEmail(), // Can be null now
                'steam_id' => $steamUser->getId(),
                'password' => Hash::make(uniqid()), // Random password since they won't use it
            ]);

            // Fetch and store Steam profile data for new user
            $this->fetchAndStoreSteamProfile($user);
            $this->fetchAndStoreFaceITProfileAction->execute($user);

            $token = $user->createToken('auth_token')->plainTextToken;
            Log::info('Created new user with ID: '.$user->id.', token generated');

            // Redirect to frontend with token
            $redirectUrl = config('app.frontend_url').'/steam-callback?token='.$token.'&success=true&message='.urlencode('Steam account created successfully');
            Log::info('Redirecting to: '.$redirectUrl);

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url').'/steam-callback?error=authentication_failed&message='.urlencode('Steam authentication failed: '.$e->getMessage()));
        }
    }

    /**
     * Link Steam account to authenticated user
     */
    public function linkSteam(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if ($user->steam_id) {
            return response()->json([
                'message' => 'User already has a Steam account linked',
                'error' => 'steam_already_linked',
            ], 409);
        }

        // Generate a secure hash for this user
        $linkHash = $user->getSteamLinkHash();

        // Temporarily modify the redirect URI to include the secure hash
        config(['services.steam.redirect' => config('app.url').'/api/auth/steam/callback?link='.$linkHash]);

        // Use the modified Steam redirect
        $redirectUrl = Socialite::driver('steam')->redirect()->getTargetUrl();

        return response()->json([
            'message' => 'Redirect to Steam for account linking',
            'steam_redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Unlink Steam account from authenticated user
     */
    public function unlinkSteam(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if (! $user->steam_id) {
            return response()->json([
                'message' => 'No Steam account linked to this user',
                'error' => 'no_steam_linked',
            ], 400);
        }

        $user->update(['steam_id' => null]);

        return response()->json([
            'message' => 'Steam account unlinked successfully',
        ]);
    }

    /**
     * Redirect to Discord for authentication
     */
    public function discordRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Check if Discord is configured
        if (! config('services.discord.client_id') || ! config('services.discord.client_secret')) {
            return redirect(config('app.frontend_url').'/discord-callback?error=discord_not_configured&message='.urlencode('Discord OAuth is not configured. Please set DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in your environment.'));
        }

        return Socialite::driver('discord')->redirect();
    }

    /**
     * Handle Discord authentication callback
     */
    public function discordCallback(): \Illuminate\Http\RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
            Log::info('Discord user ID: '.$discordUser->getId());

            // Check if this is a Discord account linking request (stored in session)
            $linkHash = session('discord_link_hash');
            Log::info('Discord callback - link hash: '.($linkHash ?? 'null'));

            if ($linkHash) {
                // Clear the link hash from session after use
                session()->forget('discord_link_hash');
                $currentUser = User::where('discord_link_hash', $linkHash)->first();
                Log::info('Discord linking detected for user: '.($currentUser ? $currentUser->id : 'not found'));

                if (! $currentUser) {
                    return redirect(config('app.frontend_url').'/discord-callback?error=user_not_found&message='.urlencode('User not found'));
                }

                // Check if another user already has this Discord ID
                if (User::where('discord_id', $discordUser->getId())->exists()) {
                    return redirect(config('app.frontend_url').'/discord-callback?error=discord_already_linked&message='.urlencode('This Discord account is already linked to another user'));
                }

                // Link Discord account to current user
                $currentUser->update(['discord_id' => $discordUser->getId()]);
                Log::info('Successfully linked Discord ID '.$discordUser->getId().' to user ID '.$currentUser->id);

                // Redirect to frontend with success
                return redirect(config('app.frontend_url').'/discord-callback?success=true&message='.urlencode('Discord account linked successfully'));
            }

            // Check if user already exists with this Discord ID
            $user = User::where('discord_id', $discordUser->getId())->first();

            if ($user) {
                // User exists, log them in
                $token = $user->createToken('auth_token')->plainTextToken;

                // Redirect to frontend with token
                return redirect(config('app.frontend_url').'/discord-callback?token='.$token.'&success=true&message='.urlencode('Discord login successful'));
            }

            // Create new user with Discord account
            Log::info('Creating new Discord user with ID: '.$discordUser->getId());
            $user = User::create([
                'name' => $discordUser->getName() ?? $discordUser->getNickname() ?? 'Discord User',
                'email' => $discordUser->getEmail(), // Can be null
                'discord_id' => $discordUser->getId(),
                'password' => Hash::make(uniqid()), // Random password since they won't use it
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;
            Log::info('Created new user with ID: '.$user->id.', token generated');

            // Redirect to frontend with token
            $redirectUrl = config('app.frontend_url').'/discord-callback?token='.$token.'&success=true&message='.urlencode('Discord account created successfully');
            Log::info('Redirecting to: '.$redirectUrl);

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url').'/discord-callback?error=authentication_failed&message='.urlencode('Discord authentication failed: '.$e->getMessage()));
        }
    }

    /**
     * Link Discord account to authenticated user
     */
    public function linkDiscord(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if ($user->discord_id) {
            return response()->json([
                'message' => 'User already has a Discord account linked',
                'error' => 'discord_already_linked',
            ], 409);
        }

        // Check if Discord is configured
        if (! config('services.discord.client_id') || ! config('services.discord.client_secret')) {
            return response()->json([
                'message' => 'Discord OAuth is not configured. Please set DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in your environment.',
                'error' => 'discord_not_configured',
            ], 500);
        }

        // Generate a secure hash for this user
        $linkHash = $user->getDiscordLinkHash();

        // Store the link hash in the session (Discord doesn't allow query params in redirect URI)
        session(['discord_link_hash' => $linkHash]);

        // Use the base Discord redirect (without query parameters)
        $redirectUrl = Socialite::driver('discord')->redirect()->getTargetUrl();

        return response()->json([
            'message' => 'Redirect to Discord for account linking',
            'discord_redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Unlink Discord account from authenticated user
     */
    public function unlinkDiscord(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if (! $user->discord_id) {
            return response()->json([
                'message' => 'No Discord account linked to this user',
                'error' => 'no_discord_linked',
            ], 400);
        }

        $user->update(['discord_id' => null]);

        return response()->json([
            'message' => 'Discord account unlinked successfully',
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'error' => 'invalid_current_password',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Change user username
     */
    public function changeUsername(ChangeUsernameRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->name === $request->new_username) {
            return response()->json([
                'message' => 'New username must be different from current username',
                'error' => 'same_username',
            ], 400);
        }

        $user->update([
            'name' => $request->new_username,
        ]);

        return response()->json([
            'message' => 'Username changed successfully',
            'user' => $user,
        ]);
    }

    /**
     * Change user email
     */
    public function changeEmail(ChangeEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email === $request->new_email) {
            return response()->json([
                'message' => 'New email must be different from current email',
                'error' => 'same_email',
            ], 400);
        }

        $user->update([
            'email' => $request->new_email,
        ]);

        return response()->json([
            'message' => 'Email changed successfully',
            'user' => $user,
        ]);
    }
}
