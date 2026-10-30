<?php

namespace App\Events;

use App\Models\Cause;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CauseGoalReached
{
    use Dispatchable, SerializesModels;

    public Cause $cause;

    public function __construct(Cause $cause)
    {
        $this->cause = $cause;
    }
}