<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicKey = config('services.discord.public_key');

        if (! $publicKey) {
            Log::error('Discord public key not configured');

            return response()->json(['error' => 'Discord configuration missing'], 500);
        }

        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if (! $signature || ! $timestamp) {
            Log::warning('Discord signature headers missing', [
                'has_signature' => ! empty($signature),
                'has_timestamp' => ! empty($timestamp),
                'all_headers' => $request->headers->all(),
            ]);

            return response()->json(['error' => 'Missing signature headers'], 401);
        }

        // CRITICAL: Get raw body content BEFORE Laravel parses it
        // We need to read from php://input to get the unmodified body
        // Note: php://input can only be read once per request
        $body = file_get_contents('php://input');

        // If body is empty (php://input was already read), try getContent() as fallback
        if (empty($body)) {
            $body = $request->getContent();
            Log::warning('php://input was empty, using getContent() fallback', [
                'getContent_length' => strlen($body),
            ]);
        }

        $message = $timestamp . $body;

        // Debug logging (remove in production if needed)
        Log::debug('Discord signature verification attempt', [
            'timestamp' => $timestamp,
            'timestamp_length' => strlen($timestamp),
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 100),
            'message_length' => strlen($message),
            'message_preview' => substr($message, 0, 110),
            'signature' => $signature,
            'signature_length' => strlen($signature),
            'public_key_length' => strlen($publicKey),
            'public_key_preview' => substr($publicKey, 0, 20),
        ]);

        // Verify ed25519 signature
        $isValid = $this->verifyEd25519Signature($publicKey, $signature, $message);

        if (! $isValid) {
            // Note: Discord may send duplicate requests with different signatures
            // This is normal behavior - the retry will succeed
            Log::warning('Discord signature verification failed (this may be a retry)', [
                'public_key' => substr($publicKey, 0, 10) . '...',
                'signature' => $signature,
                'signature_preview' => substr($signature, 0, 10) . '...',
                'timestamp' => $timestamp,
                'body_hash' => hash('sha256', $body),
                'message_hash' => hash('sha256', $message),
                'note' => 'Discord may retry with a different signature - this is expected behavior',
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        Log::info('Discord signature verified successfully');

        return $next($request);
    }

    /**
     * Verify ed25519 signature using sodium extension or sodium_compat
     *
     * @param  string  $publicKey  Hex-encoded public key
     * @param  string  $signature  Hex-encoded signature
     * @param  string  $message  Message to verify
     */
    private function verifyEd25519Signature(string $publicKey, string $signature, string $message): bool
    {
        // Remove any whitespace from hex strings
        $publicKey = trim($publicKey);
        $signature = trim($signature);

        // Convert hex strings to binary
        $publicKeyBin = @hex2bin($publicKey);
        $signatureBin = @hex2bin($signature);

        if ($publicKeyBin === false || $signatureBin === false) {
            Log::error('Failed to decode hex strings for Discord signature verification', [
                'public_key_valid_hex' => ctype_xdigit($publicKey),
                'signature_valid_hex' => ctype_xdigit($signature),
                'public_key_length' => strlen($publicKey),
                'signature_length' => strlen($signature),
            ]);

            return false;
        }

        // Verify public key length (ed25519 public keys are 32 bytes)
        if (strlen($publicKeyBin) !== 32) {
            Log::error('Invalid public key length', [
                'expected' => 32,
                'actual' => strlen($publicKeyBin),
            ]);

            return false;
        }

        // Verify signature length (ed25519 signatures are 64 bytes)
        if (strlen($signatureBin) !== 64) {
            Log::error('Invalid signature length', [
                'expected' => 64,
                'actual' => strlen($signatureBin),
            ]);

            return false;
        }

        // Use sodium extension if available
        if (extension_loaded('sodium')) {
            $result = @sodium_crypto_sign_verify_detached($signatureBin, $message, $publicKeyBin);
            if ($result === false) {
                Log::debug('sodium_crypto_sign_verify_detached returned false');
            }

            return $result;
        }

        // Fallback to sodium_compat if available
        if (class_exists('ParagonIE_Sodium_Compat')) {
            return \ParagonIE_Sodium_Compat::crypto_sign_verify_detached($signatureBin, $message, $publicKeyBin);
        }

        Log::error('No ed25519 signature verification library available');

        return false;
    }
}
