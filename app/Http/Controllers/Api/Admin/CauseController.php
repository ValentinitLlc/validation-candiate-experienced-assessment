<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCauseRequest;
use App\Http\Requests\UpdateCauseRequest;
use App\Http\Resources\CauseResource;
use App\Models\Cause;
use Illuminate\Http\JsonResponse;

class CauseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $causes = Cause::withSum('pledges', 'amount')
            ->withCount('pledges')
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return CauseResource::collection($causes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCauseRequest $request): CauseResource
    {
        $cause = Cause::create($request->validated());

        return new CauseResource($cause);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cause $cause): CauseResource
    {
        return new CauseResource($cause);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCauseRequest $request, Cause $cause): CauseResource
    {
        $this->authorize('update', $cause);
        $cause->update($request->validated());

        return new CauseResource($cause);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cause $cause): JsonResponse
    {
        $cause->delete();

        return response()->json(null, 204);
    }

    // Endpoint spécifique pour marquer/démarquer comme featured
    public function toggleFeatured(Cause $cause): CauseResource
    {
        $cause->update([
            'is_featured' => !$cause->is_featured
        ]);

        return new CauseResource($cause);
    }
}
