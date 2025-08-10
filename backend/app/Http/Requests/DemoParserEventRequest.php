<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemoParserEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by API key middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $baseRules = [
            'data' => 'required|array|min:1',
            'data.*.round_number' => 'required|integer|min:1',
            'data.*.tick_timestamp' => 'required|integer|min:0',
        ];

        // Add event-specific validation rules
        $eventRules = $this->getEventSpecificRules();

        return array_merge($baseRules, $eventRules);
    }

    /**
     * Get event-specific validation rules based on the event type
     */
    private function getEventSpecificRules(): array
    {
        $eventName = $this->route('eventName');

        return match ($eventName) {
            'round' => $this->getRoundEventRules(),
            'gunfight' => $this->getGunfightEventRules(),
            'grenade' => $this->getGrenadeEventRules(),
            'damage' => $this->getDamageEventRules(),
            default => [],
        };
    }

    /**
     * Validation rules for round events
     */
    private function getRoundEventRules(): array
    {
        return [
            'data.*.event_type' => ['required', 'string', Rule::in(['start', 'end'])],
            'data.*.winner' => 'nullable|string|in:CT,T',
            'data.*.duration' => 'nullable|integer|min:1|max:300', // Max 5 minutes
        ];
    }

    /**
     * Validation rules for gunfight events
     */
    private function getGunfightEventRules(): array
    {
        return [
            'batch_index' => 'required|integer|min:1',
            'is_last' => 'required|boolean',
            'total_batches' => 'required|integer|min:1',
            'data.*.round_time' => 'required|integer|min:0|max:300',
            'data.*.player_1_steam_id' => 'required|string|max:255',
            'data.*.player_2_steam_id' => 'required|string|max:255',
            'data.*.player_1_hp_start' => 'required|integer|min:0|max:100',
            'data.*.player_2_hp_start' => 'required|integer|min:0|max:100',
            'data.*.player_1_armor' => 'required|integer|min:0|max:100',
            'data.*.player_2_armor' => 'required|integer|min:0|max:100',
            'data.*.player_1_flashed' => 'required|boolean',
            'data.*.player_2_flashed' => 'required|boolean',
            'data.*.player_1_weapon' => 'required|string|max:50',
            'data.*.player_2_weapon' => 'required|string|max:50',
            'data.*.player_1_equipment_value' => 'required|integer|min:0|max:10000',
            'data.*.player_2_equipment_value' => 'required|integer|min:0|max:10000',
            'data.*.player_1_position.x' => 'required|numeric|between:-10000,10000',
            'data.*.player_1_position.y' => 'required|numeric|between:-10000,10000',
            'data.*.player_1_position.z' => 'required|numeric|between:-10000,10000',
            'data.*.player_2_position.x' => 'required|numeric|between:-10000,10000',
            'data.*.player_2_position.y' => 'required|numeric|between:-10000,10000',
            'data.*.player_2_position.z' => 'required|numeric|between:-10000,10000',
            'data.*.distance' => 'required|numeric|min:0|max:10000',
            'data.*.headshot' => 'required|boolean',
            'data.*.wallbang' => 'required|boolean',
            'data.*.penetrated_objects' => 'required|integer|min:0|max:100',
            'data.*.victor_steam_id' => 'nullable|string|max:255',
            'data.*.damage_dealt' => 'required|integer|min:0|max:1000',
        ];
    }

    /**
     * Validation rules for grenade events
     */
    private function getGrenadeEventRules(): array
    {
        return [
            'data.*.round_time' => 'required|integer|min:0|max:300',
            'data.*.player_steam_id' => 'required|string|max:255',
            'data.*.grenade_type' => ['required', 'string', Rule::in([
                'hegrenade',
                'flashbang',
                'smokegrenade',
                'molotov',
                'incendiary',
                'decoy'
            ])],
            'data.*.player_position.x' => 'required|numeric|between:-10000,10000',
            'data.*.player_position.y' => 'required|numeric|between:-10000,10000',
            'data.*.player_position.z' => 'required|numeric|between:-10000,10000',
            'data.*.player_aim.x' => 'required|numeric|between:-1,1',
            'data.*.player_aim.y' => 'required|numeric|between:-1,1',
            'data.*.player_aim.z' => 'required|numeric|between:-1,1',
            'data.*.grenade_final_position.x' => 'nullable|numeric|between:-10000,10000',
            'data.*.grenade_final_position.y' => 'nullable|numeric|between:-10000,10000',
            'data.*.grenade_final_position.z' => 'nullable|numeric|between:-10000,10000',
            'data.*.damage_dealt' => 'required|integer|min:0|max:1000',
            'data.*.flash_duration' => 'nullable|numeric|min:0|max:10',
            'data.*.affected_players' => 'nullable|array',
            'data.*.affected_players.*.steam_id' => 'required_with:data.*.affected_players|string|max:255',
            'data.*.affected_players.*.flash_duration' => 'nullable|numeric|min:0|max:10',
            'data.*.affected_players.*.damage_taken' => 'nullable|integer|min:0|max:1000',
            'data.*.throw_type' => ['required', 'string', Rule::in([
                'lineup',
                'reaction',
                'pre_aim',
                'utility'
            ])],
        ];
    }

    /**
     * Validation rules for damage events
     */
    private function getDamageEventRules(): array
    {
        return [
            'data.*.round_time' => 'required|integer|min:0|max:300',
            'data.*.attacker_steam_id' => 'required|string|max:255',
            'data.*.victim_steam_id' => 'required|string|max:255',
            'data.*.damage' => 'required|integer|min:0|max:1000',
            'data.*.armor_damage' => 'required|integer|min:0|max:1000',
            'data.*.health_damage' => 'required|integer|min:0|max:1000',
            'data.*.headshot' => 'required|boolean',
            'data.*.weapon' => 'required|string|max:50',
        ];
    }

    /**
     * Get custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'data.required' => 'Event data is required.',
            'data.array' => 'Event data must be an array.',
            'data.min' => 'At least one event must be provided.',
            'data.*.round_number.required' => 'Round number is required for each event.',
            'data.*.round_number.integer' => 'Round number must be an integer.',
            'data.*.round_number.min' => 'Round number must be at least 1.',
            'data.*.tick_timestamp.required' => 'Tick timestamp is required for each event.',
            'data.*.tick_timestamp.integer' => 'Tick timestamp must be an integer.',
            'data.*.tick_timestamp.min' => 'Tick timestamp must be non-negative.',
            'batch_index.required' => 'Batch index is required for gunfight events.',
            'is_last.required' => 'Is last flag is required for gunfight events.',
            'total_batches.required' => 'Total batches count is required for gunfight events.',
            'data.*.player_1_steam_id.required' => 'Player 1 Steam ID is required.',
            'data.*.player_2_steam_id.required' => 'Player 2 Steam ID is required.',
            'data.*.player_1_hp_start.required' => 'Player 1 HP start is required.',
            'data.*.player_1_hp_start.min' => 'Player 1 HP start must be at least 0.',
            'data.*.player_1_hp_start.max' => 'Player 1 HP start cannot exceed 100.',
            'data.*.player_2_hp_start.required' => 'Player 2 HP start is required.',
            'data.*.player_2_hp_start.min' => 'Player 2 HP start must be at least 0.',
            'data.*.player_2_hp_start.max' => 'Player 2 HP start cannot exceed 100.',
            'data.*.distance.required' => 'Distance between players is required.',
            'data.*.distance.numeric' => 'Distance must be a number.',
            'data.*.distance.min' => 'Distance must be non-negative.',
            'data.*.grenade_type.in' => 'Invalid grenade type. Must be one of: hegrenade, flashbang, smokegrenade, molotov, incendiary, decoy.',
            'data.*.throw_type.in' => 'Invalid throw type. Must be one of: lineup, reaction, pre_aim, utility.',
            'data.*.event_type.in' => 'Invalid event type. Must be either "start" or "end".',
            'data.*.winner.in' => 'Invalid winner. Must be either "CT" or "T".',
            'data.*.duration.min' => 'Round duration must be at least 1 second.',
            'data.*.duration.max' => 'Round duration cannot exceed 300 seconds.',
        ];
    }

    /**
     * Get custom validation attributes that should be used in error messages.
     */
    public function attributes(): array
    {
        return [
            'data' => 'event data',
            'batch_index' => 'batch index',
            'is_last' => 'is last flag',
            'total_batches' => 'total batches',
            'round_number' => 'round number',
            'tick_timestamp' => 'tick timestamp',
            'round_time' => 'round time',
            'player_1_steam_id' => 'player 1 Steam ID',
            'player_2_steam_id' => 'player 2 Steam ID',
            'player_1_hp_start' => 'player 1 HP start',
            'player_2_hp_start' => 'player 2 HP start',
            'player_1_armor' => 'player 1 armor',
            'player_2_armor' => 'player 2 armor',
            'player_1_flashed' => 'player 1 flashed',
            'player_2_flashed' => 'player 2 flashed',
            'player_1_weapon' => 'player 1 weapon',
            'player_2_weapon' => 'player 2 weapon',
            'player_1_equipment_value' => 'player 1 equipment value',
            'player_2_equipment_value' => 'player 2 equipment value',
            'player_1_position' => 'player 1 position',
            'player_2_position' => 'player 2 position',
            'distance' => 'distance',
            'headshot' => 'headshot',
            'wallbang' => 'wallbang',
            'penetrated_objects' => 'penetrated objects',
            'victor_steam_id' => 'victor Steam ID',
            'damage_dealt' => 'damage dealt',
            'player_steam_id' => 'player Steam ID',
            'grenade_type' => 'grenade type',
            'player_position' => 'player position',
            'player_aim' => 'player aim',
            'grenade_final_position' => 'grenade final position',
            'flash_duration' => 'flash duration',
            'affected_players' => 'affected players',
            'throw_type' => 'throw type',
            'attacker_steam_id' => 'attacker Steam ID',
            'victim_steam_id' => 'victim Steam ID',
            'damage' => 'damage',
            'armor_damage' => 'armor damage',
            'health_damage' => 'health damage',
            'weapon' => 'weapon',
            'event_type' => 'event type',
            'winner' => 'winner',
            'duration' => 'duration',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure data is always an array
        if (!$this->has('data')) {
            $this->merge(['data' => []]);
        }

        // Ensure batch fields are present for gunfight events
        if ($this->route('eventName') === 'gunfight') {
            if (!$this->has('batch_index')) {
                $this->merge(['batch_index' => 1]);
            }
            if (!$this->has('is_last')) {
                $this->merge(['is_last' => true]);
            }
            if (!$this->has('total_batches')) {
                $this->merge(['total_batches' => 1]);
            }
        }
    }
}
