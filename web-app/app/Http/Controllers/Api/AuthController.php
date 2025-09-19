<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
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
            \Log::info('Steam user ID: '.$steamUser->getId());

            // Check if user already exists with this Steam ID
            $user = User::where('steam_id', $steamUser->getId())->first();

            if ($user) {
                // User exists, log them in
                $token = $user->createToken('auth_token')->plainTextToken;

                // Redirect to frontend with token
                return redirect('http://localhost:8000/steam-callback?token='.$token.'&success=true&message='.urlencode('Steam login successful'));
            }

            // Check if this is a Steam account linking request
            $linkHash = request('link');
            \Log::info('Steam callback - link hash: '.($linkHash ?? 'null'));

            if ($linkHash) {
                $currentUser = User::where('steam_link_hash', $linkHash)->first();
                \Log::info('Steam linking detected for user: '.($currentUser ? $currentUser->id : 'not found'));

                if (! $currentUser) {
                    return redirect('http://localhost:8000/steam-callback?error=user_not_found&message='.urlencode('User not found'));
                }

                // Check if another user already has this Steam ID
                if (User::where('steam_id', $steamUser->getId())->exists()) {
                    return redirect('http://localhost:8000/steam-callback?error=steam_already_linked&message='.urlencode('This Steam account is already linked to another user'));
                }

                // Link Steam account to current user
                User::where('id', $currentUser->id)->update(['steam_id' => $steamUser->getId()]);
                \Log::info('Successfully linked Steam ID '.$steamUser->getId().' to user ID '.$currentUser->id);

                // Redirect to frontend with success
                return redirect('http://localhost:8000/steam-callback?success=true&message='.urlencode('Steam account linked successfully'));
            }

            // Create new user with Steam account
            \Log::info('Creating new Steam user with ID: '.$steamUser->getId());
            $user = User::create([
                'name' => $steamUser->getNickname() ?? 'Steam User',
                'email' => $steamUser->getEmail(), // Can be null now
                'steam_id' => $steamUser->getId(),
                'password' => Hash::make(uniqid()), // Random password since they won't use it
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;
            \Log::info('Created new user with ID: '.$user->id.', token generated');

            // Redirect to frontend with token
            $redirectUrl = 'http://localhost:8000/steam-callback?token='.$token.'&success=true&message='.urlencode('Steam account created successfully');
            \Log::info('Redirecting to: '.$redirectUrl);

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            return redirect('http://localhost:8000/steam-callback?error=authentication_failed&message='.urlencode('Steam authentication failed: '.$e->getMessage()));
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
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

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
    public function changeUsername(Request $request): JsonResponse
    {
        $request->validate([
            'new_username' => 'required|string|min:2|max:50|unique:users,name',
        ]);

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
    public function changeEmail(Request $request): JsonResponse
    {
        $request->validate([
            'new_email' => 'required|email|unique:users,email',
        ]);

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
