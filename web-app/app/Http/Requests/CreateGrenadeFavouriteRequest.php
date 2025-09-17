<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGrenadeFavouriteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'match_id' => 'required|exists:matches,id',
            'round_number' => 'required|integer|min:1',
            'round_time' => 'required|numeric|min:0',
            'tick_timestamp' => 'required|integer|min:0',
            'player_steam_id' => 'required|string',
            'player_side' => 'required|in:T,CT',
            'grenade_type' => 'required|string',
            'player_x' => 'required|numeric',
            'player_y' => 'required|numeric',
            'player_z' => 'required|numeric',
            'player_aim_x' => 'required|numeric',
            'player_aim_y' => 'required|numeric',
            'player_aim_z' => 'required|numeric',
            'grenade_final_x' => 'required|numeric',
            'grenade_final_y' => 'required|numeric',
            'grenade_final_z' => 'required|numeric',
            'damage_dealt' => 'nullable|numeric|min:0',
            'flash_duration' => 'nullable|numeric|min:0',
            'friendly_flash_duration' => 'nullable|numeric|min:0',
            'enemy_flash_duration' => 'nullable|numeric|min:0',
            'friendly_players_affected' => 'nullable|integer|min:0',
            'enemy_players_affected' => 'nullable|integer|min:0',
            'throw_type' => 'nullable|string',
            'effectiveness_rating' => 'nullable|numeric',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_id.required' => 'A match ID is required.',
            'match_id.exists' => 'The specified match does not exist.',
            'round_number.required' => 'A round number is required.',
            'round_number.integer' => 'Round number must be an integer.',
            'round_number.min' => 'Round number must be at least 1.',
            'round_time.required' => 'Round time is required.',
            'round_time.numeric' => 'Round time must be a number.',
            'round_time.min' => 'Round time must be at least 0.',
            'tick_timestamp.required' => 'Tick timestamp is required.',
            'tick_timestamp.integer' => 'Tick timestamp must be an integer.',
            'tick_timestamp.min' => 'Tick timestamp must be at least 0.',
            'player_steam_id.required' => 'Player Steam ID is required.',
            'player_side.required' => 'Player side is required.',
            'player_side.in' => 'Player side must be either T or CT.',
            'grenade_type.required' => 'Grenade type is required.',
            'player_x.required' => 'Player X coordinate is required.',
            'player_x.numeric' => 'Player X coordinate must be a number.',
            'player_y.required' => 'Player Y coordinate is required.',
            'player_y.numeric' => 'Player Y coordinate must be a number.',
            'player_z.required' => 'Player Z coordinate is required.',
            'player_z.numeric' => 'Player Z coordinate must be a number.',
            'player_aim_x.required' => 'Player aim X coordinate is required.',
            'player_aim_x.numeric' => 'Player aim X coordinate must be a number.',
            'player_aim_y.required' => 'Player aim Y coordinate is required.',
            'player_aim_y.numeric' => 'Player aim Y coordinate must be a number.',
            'player_aim_z.required' => 'Player aim Z coordinate is required.',
            'player_aim_z.numeric' => 'Player aim Z coordinate must be a number.',
            'grenade_final_x.required' => 'Grenade final X coordinate is required.',
            'grenade_final_x.numeric' => 'Grenade final X coordinate must be a number.',
            'grenade_final_y.required' => 'Grenade final Y coordinate is required.',
            'grenade_final_y.numeric' => 'Grenade final Y coordinate must be a number.',
            'grenade_final_z.required' => 'Grenade final Z coordinate is required.',
            'grenade_final_z.numeric' => 'Grenade final Z coordinate must be a number.',
            'damage_dealt.numeric' => 'Damage dealt must be a number.',
            'damage_dealt.min' => 'Damage dealt must be at least 0.',
            'flash_duration.numeric' => 'Flash duration must be a number.',
            'flash_duration.min' => 'Flash duration must be at least 0.',
            'friendly_flash_duration.numeric' => 'Friendly flash duration must be a number.',
            'friendly_flash_duration.min' => 'Friendly flash duration must be at least 0.',
            'enemy_flash_duration.numeric' => 'Enemy flash duration must be a number.',
            'enemy_flash_duration.min' => 'Enemy flash duration must be at least 0.',
            'friendly_players_affected.integer' => 'Friendly players affected must be an integer.',
            'friendly_players_affected.min' => 'Friendly players affected must be at least 0.',
            'enemy_players_affected.integer' => 'Enemy players affected must be an integer.',
            'enemy_players_affected.min' => 'Enemy players affected must be at least 0.',
            'effectiveness_rating.numeric' => 'Effectiveness rating must be a number.',
            'effectiveness_rating.min' => 'Effectiveness rating must be at least 0.',
            'effectiveness_rating.max' => 'Effectiveness rating cannot exceed 10.',
        ];
    }
}
