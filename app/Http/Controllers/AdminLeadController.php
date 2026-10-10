<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Support\YandexConversionExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function storeOffline(Request $request, YandexConversionExport $export)
    {
        $validated = $request->validate([
            'offline_type' => 'required|string|in:call,email',
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'ad_id' => ['nullable', 'string', 'regex:/^[0-9]{1,32}$/D'],
            'comment' => 'nullable|string|max:2000',
        ], [
            'ad_id.regex' => 'Номер визита должен содержать от 1 до 32 цифр.',
        ]);

        $validContact = $validated['offline_type'] === 'call'
            ? $export->normalizePhone($validated['contact'])
            : $export->normalizeEmail($validated['contact']);

        if ($validContact === null) {
            return response()->json([
                'success' => false,
                'message' => $validated['offline_type'] === 'call'
                    ? 'Укажите номер телефона с кодом страны.'
                    : 'Укажите корректный адрес электронной почты.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

    public function exportOffline(Request $request, YandexConversionExport $export): StreamedResponse
    {
        $validated = $request->validate([
            'period' => 'required|string|in:'.implode(',', YandexConversionExport::PERIODS),
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ]);

        [$start, $end] = $export->periodRange($validated['period'], $validated['date']);
        $query = Lead::query()
            ->where('channel', 'offline')
            ->whereIn('type', ['offline_call', 'offline_email'])
            ->orderBy('created_at')
            ->orderBy('id');

        if ($start->lessThanOrEqualTo($end)) {
            $query->whereBetween('created_at', [$start, $end]);
        } else {
            $query->whereRaw('1 = 0');
        }

        $filename = sprintf(
            'yandex-offline-conversions-%s-%s.csv',
            $validated['period'],
            $validated['date'],
        );

        return response()->streamDownload(
            static function () use ($export, $query): void {
                foreach ($export->lines($query->cursor()) as $line) {
                    echo $line;
                }
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
