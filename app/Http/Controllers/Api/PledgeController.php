<?php

namespace App\Http\Controllers\Api;

use App\Events\CauseGoalReached;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePledgeRequest;
use App\Http\Resources\PledgeResource;
use App\Models\Cause;
use App\Models\Pledge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PledgeController extends Controller
{
    public function store(StorePledgeRequest $request): PledgeResource
    {
        $pledge = DB::transaction(function () use ($request) {
            // Lock pessimiste pour empêcher les accès concurrents
            $cause = Cause::where('id', $request->validated('cause_id'))
                ->lockForUpdate()
                ->firstOrFail();

            // Re-vérification après verrouillage (thread-safe)
            if (! $cause->is_active) {
                throw ValidationException::withMessages([
                    'cause_id' => ['Pledges cannot be accepted for an inactive Cause.'],
                ]);
            }

            // Enregistrement de la promesse de don
            $pledge = Pledge::create($request->validated());
            
            // Calcul du total cumulé en SQL (pas de boucle PHP)
            $totalPledged = $cause->pledges()->sum('amount');

            // Détection du franchissement de l'objectif (transition EXACTLY ONCE garantie)
            if ($totalPledged >= $cause->goal_amount && $cause->is_active) {
                // On désactive uniquement si elle était encore active (évite les écritures et déclenchements multiples)
                $cause->update(['is_active' => false]);
                
                event(new CauseGoalReached($cause));
            }

            return $pledge;
        });

        return new PledgeResource($pledge);
    }
}