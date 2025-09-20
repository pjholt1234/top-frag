<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSteamSharecodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_steam_sharecode(): void
    {
        $user = User::factory()->create([
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_sharecode_added_at' => now(),
            'steam_match_processing_enabled' => true,
        ]);

        $this->assertTrue($user->hasSteamSharecode());
        $this->assertEquals('CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY', $user->steam_sharecode);
        $this->assertNotNull($user->steam_sharecode_added_at);
        $this->assertTrue($user->steam_match_processing_enabled);
    }

    public function test_user_without_sharecode_returns_false(): void
    {
        $user = User::factory()->create([
            'steam_sharecode' => null,
        ]);

        $this->assertFalse($user->hasSteamSharecode());
    }

    public function test_user_with_empty_sharecode_returns_false(): void
    {
        $user = User::factory()->create([
            'steam_sharecode' => '',
        ]);

        $this->assertFalse($user->hasSteamSharecode());
    }

    public function test_is_valid_sharecode_validates_correct_format(): void
    {
        $validSharecodes = [
            'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO',
            'CSGO-00000-11111-22222-33333-44444',
        ];

        foreach ($validSharecodes as $sharecode) {
            $this->assertTrue(User::isValidSharecode($sharecode), "Failed for: {$sharecode}");
        }
    }

    public function test_is_valid_sharecode_rejects_invalid_format(): void
    {
        $invalidSharecodes = [
            'CSGO-ABCD-FGHIJ-KLMNO-PQRST-UVWXY', // Too short
            'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXYZ', // Too long
            'csgo-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY', // Wrong case
            'CSGO_ABCDE_FGHIJ_KLMNO_PQRST_UVWXY', // Wrong separator
            'CSGO-ABCDE-FGHIJ-KLMNO-PQRST', // Missing segment
            'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY-EXTRA', // Extra segment
            'INVALID-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY', // Wrong prefix
            '', // Empty
            'not-a-sharecode', // Completely wrong
        ];

        foreach ($invalidSharecodes as $sharecode) {
            $this->assertFalse(User::isValidSharecode($sharecode), "Should have failed for: {$sharecode}");
        }
    }

    public function test_steam_sharecode_fields_are_fillable(): void
    {
        $user = User::factory()->create();

        $sharecodeData = [
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_sharecode_added_at' => now(),
            'steam_match_processing_enabled' => true,
        ];

        $user->update($sharecodeData);

        $this->assertEquals($sharecodeData['steam_sharecode'], $user->fresh()->steam_sharecode);
        $this->assertEquals($sharecodeData['steam_sharecode_added_at']->format('Y-m-d H:i:s'), $user->fresh()->steam_sharecode_added_at->format('Y-m-d H:i:s'));
        $this->assertTrue($user->fresh()->steam_match_processing_enabled);
    }

    public function test_steam_sharecode_added_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create([
            'steam_sharecode_added_at' => '2024-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->steam_sharecode_added_at);
    }

    public function test_steam_match_processing_enabled_is_cast_to_boolean(): void
    {
        $user = User::factory()->create([
            'steam_match_processing_enabled' => 1,
        ]);

        $this->assertTrue($user->steam_match_processing_enabled);
        $this->assertIsBool($user->steam_match_processing_enabled);
    }
}
