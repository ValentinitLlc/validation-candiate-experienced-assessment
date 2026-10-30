<?php

namespace App\Listeners;

use App\Events\CauseGoalReached;
use App\Notifications\CauseGoalReachedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendGoalReachedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(CauseGoalReached $event): void
    {
        // Envoi de la notification asynchrone
        $adminEmail = config('mail.admin_address', 'admin@example.com');
        
        Notification::route('mail', $adminEmail)
            ->notify(new CauseGoalReachedNotification($event->cause));
    }
}
    