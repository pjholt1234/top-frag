<?php

namespace App\Observers;

use App\Models\DamageEvent;

class DamageEventObserver
{
    public function created(DamageEvent $damageEvent): void
    {
        $damageEvent->match?->invalidateMatchCache();
    }
}
