<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    /** Koliko faktura stane na stranicu. */
    private const PO_STRANICI = 25;

    public function __construct(private readonly PaymentProcessor $payments) {}

    /**
     * Fakture sa razdvojenim radom i materijalom, uz vezu na nalog i pretplatu.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(InvoiceStatus::values())],
            'type' => ['nullable', Rule::in(InvoiceType::values())],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));

        $osnova = fn () => Invoice::query()
            ->when($q !== '', fn ($query) => $query->where(function ($where) use ($q) {
                $where->where('number', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%'));
            }))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type));

        $stranica = $osnova()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['user', 'job', 'subscription.package'])
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? self::PO_STRANICI));

        $counts = $osnova()
            ->groupBy('status')
            ->selectRaw('status, count(*) as ukupno')
            ->pluck('ukupno', 'status');

        return response()->json([
            'data' => $stranica->getCollection()->map(fn (Invoice $invoice) => $this->red($invoice))->values(),
            'meta' => [
                'counts' => $this->brojaci($counts->all()),
                'total' => $stranica->total(),
                'per_page' => $stranica->perPage(),
                'current_page' => $stranica->currentPage(),
                'last_page' => $stranica->lastPage(),
            ],
        ]);
    }

    /**
     * Pun ili djelimican povrat, uvijek kroz gateway.
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()->with(['user', 'job', 'subscription.package'])->find($id);

        if (! $invoice) {
            return response()->json(['message' => 'Faktura nije pronađena.'], 404);
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ], [
            'amount.numeric' => 'Iznos povrata mora biti broj.',
            'amount.min' => 'Iznos povrata mora biti veći od nule.',
        ]);

        $vraceno = $this->payments->refund(
            $invoice,
            isset($validated['amount']) ? (float) $validated['amount'] : null
        );

        return response()->json([
            'data' => $this->red($vraceno->load(['user', 'job', 'subscription.package'])),
            'message' => $vraceno->status === InvoiceStatus::Refundirano
                ? 'Povrat je proveden u cijelosti.'
                : 'Djelimičan povrat je proveden.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function red(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'type' => $invoice->type->value,
            'status' => $invoice->status->value,
            'labor_total' => (float) $invoice->labor_total,
            'material_total' => (float) $invoice->material_total,
            'total' => (float) $invoice->total,
            'refunded_amount' => (float) $invoice->refunded_amount,
            'client' => [
                'id' => $invoice->user?->id,
                'name' => $invoice->user?->name,
                'email' => $invoice->user?->email,
            ],
            'job' => $invoice->job ? [
                'id' => $invoice->job->id,
                'number' => $invoice->job->number,
                'status' => $invoice->job->status->value,
            ] : null,
            'subscription' => $invoice->subscription ? [
                'id' => $invoice->subscription->id,
                'package' => $invoice->subscription->package?->name,
                'status' => $invoice->subscription->status->value,
            ] : null,
            'sent_at' => $invoice->sent_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function brojaci(array $counts): array
    {
        $out = [];

        foreach (InvoiceStatus::values() as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        $out['ukupno'] = array_sum($out);

        return $out;
    }
}
