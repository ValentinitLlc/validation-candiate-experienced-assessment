<?php

namespace App\Http\Controllers\Api;

use App\Events\CauseGoalReached;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePledgeRequest;
use App\Http\Resources\PledgeResource;
use App\Models\Cause;
use App\Models\Pledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PledgeController extends Controller
{
    public function store(StorePledgeRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        // Vérification de l'idempotence via le cache (si la clé est fournie)
        if ($idempotencyKey) {
            $cacheKey = 'idempotency:' . $idempotencyKey;
            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);
                return response()->json($cachedResponse['data'], $cachedResponse['status']);
            }
        }

        // Traitement transactionnel et sécurisé (concurrence & logique métier)
        $pledge = DB::transaction(function () use ($request) {
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
                $cause->update(['is_active' => false]);
                
                event(new CauseGoalReached($cause));
            }

            return $pledge;
        });

        // Construction de la réponse normalisée
        $responseContent = new PledgeResource($pledge);

        // 3. Mise en cache de la réponse d'idempotence pour 24 heures si la clé est présente
        if ($idempotencyKey) {
            Cache::put($cacheKey, [
                'status' => 201,
                'data' => $responseContent
            ], now()->addDay());
        }

        return response()->json($responseContent, 201);
    }
}