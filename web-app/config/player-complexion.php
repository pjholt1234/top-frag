<?php

return [
    'opener' => [
        'average_round_time_of_death' => [
            'score' => 25,
            'higher_better' => false,
            'weight' => 1.0,
        ],
        'average_time_to_contact' => [
            'score' => 20,
            'higher_better' => false,
            'weight' => 3.0,
        ],
        'first_kills_plus_minus' => [
            'score' => 3,
            'higher_better' => true,
            'weight' => 5.0,
        ],
        'first_kill_attempts' => [
            'score' => 4,
            'higher_better' => true,
            'weight' => 4.0,
        ],
        'traded_death_percentage' => [
            'score' => 50,
            'higher_better' => true,
            'weight' => 2.0,
        ],
    ],
    'closer' => [
        'average_round_time_to_death' => [
            'score' => 40,
            'higher_better' => true,
            'weight' => 1.0,
        ],
        'average_round_time_to_contact' => [
            'score' => 35,
            'higher_better' => true,
            'weight' => 1.0,
        ],
        'clutch_win_percentage' => [
            'score' => 25,
            'higher_better' => true,
            'weight' => 4.0,
        ],
        'total_clutch_attempts' => [
            'score' => 5,
            'higher_better' => true,
            'weight' => 2.0,
        ],
    ],
    'support' => [
        'total_grenades_thrown' => [
            'score' => 25,
            'higher_better' => true,
            'weight' => 1.0,
        ],
        'damage_dealt_from_grenades' => [
            'score' => 200,
            'higher_better' => true,
            'weight' => 2.0,
        ],
        'enemy_flash_duration' => [
            'score' => 30,
            'higher_better' => true,
            'weight' => 2.0,
        ],
        'average_grenade_effectiveness' => [
            'score' => 50,
            'higher_better' => true,
            'weight' => 5.0,
        ],
        'total_flashes_leading_to_kills' => [
            'score' => 5,
            'higher_better' => true,
            'weight' => 2.0,
        ],
    ],
    'fragger' => [
        'kill_death_ratio' => [
            'score' => 1.5,
            'higher_better' => true,
            'weight' => 2.0,
        ],
        'total_kills_per_round' => [
            'score' => 0.9,
            'higher_better' => true,
            'weight' => 4.0,
        ],
        'average_damage_per_round' => [
            'score' => 90,
            'higher_better' => true,
            'weight' => 3.0,
        ],
        'trade_kill_percentage' => [
            'score' => 50,
            'higher_better' => true,
            'weight' => 3.0,
        ],
        'trade_opportunities_per_round' => [
            'score' => 1.5,
            'higher_better' => true,
            'weight' => 1.0,
        ],
    ],
];
