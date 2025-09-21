<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class SharecodeDecoderService
{
    private const DICTIONARY = 'ABCDEFGHJKLMNOPQRSTUVWXYZabcdefhijkmnopqrstuvwxyz23456789';

    private const DICTIONARY_LENGTH = 57;

    /**
     * Decode a CS:GO sharecode to extract match information
     */
    public function decode(string $sharecode): ?array
    {
        try {
            // Remove CSGO- prefix and dashes
            $code = str_replace(['CSGO-', '-'], '', $sharecode);

            if (strlen($code) !== 25) {
                Log::error('Invalid sharecode length', ['sharecode' => $sharecode, 'length' => strlen($code)]);

                return null;
            }

            // Reverse the sharecode
            $reversedCode = strrev($code);

            // Convert to number using base-57 arithmetic with string handling for large numbers
            $num = '0';
            for ($i = 0; $i < strlen($reversedCode); $i++) {
                $char = $reversedCode[$i];
                $index = strpos(self::DICTIONARY, $char);

                if ($index === false) {
                    Log::error('Invalid character in sharecode', ['sharecode' => $sharecode, 'char' => $char]);

                    return null;
                }

                $num = bcmul($num, (string) self::DICTIONARY_LENGTH);
                $num = bcadd($num, (string) $index);
            }

            // Convert number to 18-byte array (big-endian)
            $byteArray = $this->stringToBytes($num, 18);

            if (strlen($byteArray) < 18) {
                Log::error('Decoded sharecode too short', ['sharecode' => $sharecode, 'length' => strlen($byteArray)]);

                return null;
            }

            // Extract match ID (8 bytes, big-endian)
            $matchId = $this->unpackUint64BigEndian(substr($byteArray, 0, 8));

            // Extract outcome ID (8 bytes, big-endian)
            $outcomeId = $this->unpackUint64BigEndian(substr($byteArray, 8, 8));

            // Extract token ID (2 bytes, big-endian)
            $tokenId = $this->unpackUint16BigEndian(substr($byteArray, 16, 2));

            return [
                'match_id' => $matchId,
                'outcome_id' => $outcomeId,
                'token_id' => $tokenId,
            ];
        } catch (Exception $e) {
            Log::error('Failed to decode sharecode', [
                'sharecode' => $sharecode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build demo URL from decoded sharecode data
     */
    public function buildDemoUrl(array $decodedData, int $serverNumber = 1): string
    {
        $matchId = $decodedData['match_id'];
        $outcomeId = $decodedData['outcome_id'];
        $tokenId = $decodedData['token_id'];

        return "https://replay{$serverNumber}.valve.net/730/{$matchId}_{$outcomeId}_{$tokenId}.dem.bz2";
    }

    /**
     * Try to find the correct server number by testing different servers
     */
    public function findCorrectServer(array $decodedData): ?int
    {
        // Try common server numbers
        $serversToTry = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];

        foreach ($serversToTry as $serverNumber) {
            $url = $this->buildDemoUrl($decodedData, $serverNumber);

            Log::info('Testing server number', [
                'server_number' => $serverNumber,
                'url' => $url,
            ]);

            // Test if the URL is accessible
            if ($this->testUrl($url)) {
                Log::info('Found correct server number', [
                    'server_number' => $serverNumber,
                    'url' => $url,
                ]);

                return $serverNumber;
            }
        }

        Log::warning('Could not find correct server number for sharecode', [
            'match_id' => $decodedData['match_id'],
            'outcome_id' => $decodedData['outcome_id'],
        ]);

        return null;
    }

    /**
     * Convert string number to byte array (big-endian)
     */
    private function stringToBytes(string $num, int $length): string
    {
        $bytes = '';
        for ($i = $length - 1; $i >= 0; $i--) {
            $divisor = bcpow('256', (string) $i);
            $byte = bcdiv($num, $divisor);
            $bytes .= chr((int) $byte);
            $num = bcmod($num, $divisor);
        }

        return $bytes;
    }

    /**
     * Unpack 64-bit unsigned integer (big-endian)
     */
    private function unpackUint64BigEndian(string $data): string
    {
        // Convert to hex and then to decimal string to handle large unsigned integers
        $hex = bin2hex($data);

        return $this->hexToDecimal($hex);
    }

    /**
     * Unpack 16-bit unsigned integer (big-endian)
     */
    private function unpackUint16BigEndian(string $data): int
    {
        $unpacked = unpack('n', $data); // n = 16-bit big-endian

        return $unpacked[1];
    }

    /**
     * Convert hex string to decimal string
     */
    private function hexToDecimal(string $hex): string
    {
        $decimal = '0';
        $hexLength = strlen($hex);

        for ($i = 0; $i < $hexLength; $i++) {
            $decimal = bcmul($decimal, '16');
            $decimal = bcadd($decimal, (string) hexdec($hex[$i]));
        }

        return $decimal;
    }

    /**
     * Test if a URL is accessible (simple HEAD request)
     */
    private function testUrl(string $url): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 3,
                    'ignore_errors' => true,
                    'user_agent' => 'Mozilla/5.0 (compatible; CS:GO Demo Downloader)',
                ],
            ]);

            $headers = @get_headers($url, 1, $context);

            if ($headers && isset($headers[0])) {
                $statusCode = (int) substr($headers[0], 9, 3);
                Log::debug('URL test result', [
                    'url' => $url,
                    'status_code' => $statusCode,
                ]);

                return $statusCode === 200;
            }

            Log::debug('URL test failed - no headers', ['url' => $url]);

            return false;
        } catch (Exception $e) {
            Log::debug('URL test exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
