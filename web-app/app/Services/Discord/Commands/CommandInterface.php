<?php

namespace App\Services\Discord\Commands;

interface CommandInterface
{
    /**
     * Execute the Discord command.
     *
     * @param  array  $payload  Discord interaction payload
     * @return array Discord interaction response
     */
    public function execute(array $payload): array;
}
