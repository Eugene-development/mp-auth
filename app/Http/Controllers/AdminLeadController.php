<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminLeadController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);
        $leads = Lead::query()
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'leads' => $leads,
            'summary' => [
                'total' => Lead::query()->count(),
                'online' => Lead::query()->where('channel', 'online')->count(),
                'offline' => Lead::query()->where('channel', 'offline')->count(),
            ],
        ]);
    }

    public function storeOffline(Request $request)
    {
        $validated = $request->validate([
            'offline_type' => 'required|string|in:call,email',
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'ad_id' => ['nullable', 'string', 'regex:/^\d{1,32}$/'],
            'comment' => 'nullable|string|max:2000',
        ]);

        $lead = Lead::query()->create([
            'channel' => 'offline',
            'type' => 'offline_'.$validated['offline_type'],
            'delivery_status' => 'recorded',
            'ad_id' => $validated['ad_id'] ?? null,
            'name' => $validated['name'],
            'contact' => $validated['contact'],
            'comment' => $validated['comment'] ?? null,
            'captcha_verified' => false,
            'created_by_user_id' => $request->user()?->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Офлайн-заявка добавлена.',
            'lead' => $lead,
        ], Response::HTTP_CREATED);
    }
}
