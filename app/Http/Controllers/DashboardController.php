<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $nextMonth = $monthStart->addMonth();
        $entries = AccountingEntry::query()
            ->whereHas('document', fn ($query) => $query->where('user_id', $user->id));

        $recentDocuments = $user->documents()
            ->latest()
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'supplier_name' => $document->supplier_name,
                'invoice_number' => $document->invoice_number,
                'invoice_date' => $document->invoice_date?->toDateString(),
                'total_amount' => $document->total_amount,
                'currency' => $document->currency,
                'status' => $document->status,
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'to_review' => $user->documents()->where('status', Document::STATUS_NEEDS_REVIEW)->count(),
                'posted_this_month' => (clone $entries)
                    ->where('created_at', '>=', $monthStart)
                    ->where('created_at', '<', $nextMonth)
                    ->count(),
                'expenses_this_month' => (float) (clone $entries)
                    ->where('created_at', '>=', $monthStart)
                    ->where('created_at', '<', $nextMonth)
                    ->sum('total_amount'),
                'documents_this_month' => $user->documents()
                    ->where('created_at', '>=', $monthStart)
                    ->where('created_at', '<', $nextMonth)
                    ->count(),
            ],
            'recentDocuments' => $recentDocuments,
            'monthlySpend' => $this->monthlySpend($user->id),
        ]);
    }

    /** @return Collection<int, array{label: string, amount: float}> */
    private function monthlySpend(int $userId): Collection
    {
        $firstMonth = CarbonImmutable::now()->startOfMonth()->subMonths(5);

        return collect(range(0, 5))->map(function (int $offset) use ($firstMonth, $userId): array {
            $month = $firstMonth->addMonths($offset);

            return [
                'label' => ucfirst($month->translatedFormat('M')),
                'amount' => (float) AccountingEntry::query()
                    ->whereHas('document', fn ($query) => $query->where('user_id', $userId))
                    ->where('created_at', '>=', $month->startOfMonth())
                    ->where('created_at', '<', $month->addMonth()->startOfMonth())
                    ->sum('total_amount'),
            ];
        });
    }
}
