<?php

namespace Database\Factories\DataFactories;

interface DataFactoryInterface
{
    public static function create(?int $count = null, array $attributes = []): array;
}
