<?php

namespace App\Observers;

use App\Models\GunfightEvent;

class GunfightEventObserver
{
    public function created(GunfightEvent $gunfightEvent): void
    {
        $gunfightEvent->match?->invalidateMatchCache();
    }
}
