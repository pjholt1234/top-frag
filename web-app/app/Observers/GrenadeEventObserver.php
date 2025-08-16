<?php

namespace App\Observers;

use App\Models\GrenadeEvent;

class GrenadeEventObserver
{
    public function created(GrenadeEvent $grenadeEvent): void
    {
        $grenadeEvent->match->invalidateMatchCache();
    }
}
