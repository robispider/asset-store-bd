<?php

namespace GovStore\StoreOperations\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;

/**
 * The store documents hub: one office's documents, what needs the signed-in person, and the details panel.
 * Every query is pinned to the working office, so nothing listed here leads to a document the workspace refuses.
 */
class DocumentHub
{
    public const FILTERS = ['all', 'drafts', 'ready', 'posted'];

    public const TYPES = ['receipt', 'issue', 'transfer', 'adjustment'];

    public const PER_PAGE = 20;

    private const T = 'storeops::storeops.hub.';

    public function __construct(protected DocumentValidationService $validation) {}

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'filter' => 'nullable|in:'.implode(',', self::FILTERS),
            'type' => 'nullable|in:all,'.implode(',', self::TYPES),
            'q' => 'nullable|string|max:100',
            'doc' => 'nullable|uuid',
            'start' => 'nullable|in:'.implode(',', self::TYPES),
        ]);

        return [
            'filter' => $data['filter'] ?? 'all',
            'type' => $data['type'] ?? 'all',
            'q' => trim((string) ($data['q'] ?? '')),
            'doc' => $data['doc'] ?? null,
            'start' => $data['start'] ?? null,
        ];
    }

    /** Query string for a hub link: defaults dropped, the details panel and start dialog never carried over. */
    public static function query(array $filters, array $changes = []): array
    {
        $merged = array_merge(['filter' => $filters['filter'], 'type' => $filters['type'], 'q' => $filters['q']], $changes);

        return array_filter($merged, fn ($value, $key) => ! in_array($value, [null, '', 'all'], true), ARRAY_FILTER_USE_BOTH);
    }

    public function counts(int $locationId, int $userId): array
    {
        $counts = [];
        foreach (self::FILTERS as $filter) {
            $counts[$filter] = $this->scoped($this->base($locationId), $filter, $userId)->count();
        }
        $counts['posted_month'] = $this->base($locationId)->where('status', 'POSTED')
            ->where('posted_at', '>=', now()->startOfMonth())->count();

        return $counts;
    }

    public function documents(int $locationId, int $userId, array $filters): LengthAwarePaginator
    {
        $q = $filters['q'];
        $like = '%'.addcslashes($q, '%_\\').'%';

        return $this->scoped($this->base($locationId), $filters['filter'], $userId)
            ->with(['creator', 'manager', 'poster', 'recipient', 'destination', 'source', 'supplier', 'references'])
            ->withCount('items')
            ->when($filters['type'] !== 'all', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('document_number', 'like', $like)
                ->orWhere('issue_department', 'like', $like)
                ->orWhereHas('references', fn (Builder $ref) => $ref->where('reference_number', 'like', $like))))
            ->orderByDesc('updated_at')->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)->withQueryString();
    }

    public function row(Document $document, int $userId): array
    {
        $status = strtolower($document->status);
        $mine = $document->status === 'DRAFT' && (int) $document->managed_by === $userId;
        $handler = match ($document->status) {
            'POSTED' => $document->poster ?? $document->creator,
            default => $document->manager ?? $document->creator,
        };

        return [
            'id' => $document->id,
            'type' => $document->type,
            'number' => $document->getDocumentNumber(),
            'type_label' => $this->typeLabel($document->type),
            'status' => $status,
            'status_label' => __(self::T.'statuses.'.$status),
            'mine' => $mine,
            'handler' => $handler?->present()->fullName ?? __('storeops::storeops.system'),
            'party' => $this->party($document),
            'reference' => $document->references->pluck('reference_number')->filter()->implode(' / ') ?: null,
            'lines' => (int) ($document->items_count ?? $document->items()->count()),
            'activity' => $this->moment($document->updated_at ?? $document->created_at),
            'workspace' => route('storeops.documents.workspace', ['type' => $document->type, 'id' => $document->id]),
            'action' => match (true) {
                $mine => 'continue',
                $document->status === 'READY' => 'verify',
                default => 'view',
            },
        ];
    }

    /** Everything the details panel shows: progress, facts, what is still missing and the audit trail. */
    public function panel(Document $document, int $userId): array
    {
        $document->loadMissing(['creator', 'manager', 'poster', 'recipient', 'destination', 'source', 'supplier', 'references'])->loadCount('items');
        $row = $this->row($document, $userId);
        $order = ['DRAFT', 'READY', 'POSTED'];
        $at = array_search($document->status, $order, true);
        $steps = [];
        foreach ($order as $i => $state) {
            $steps[] = [
                'label' => __(self::T.'steps.'.strtolower($state)),
                'state' => $document->status === 'CANCELLED'
                    ? ($i === 0 ? 'blocked' : 'upcoming')
                    : ($i < $at || $document->status === 'POSTED' ? 'done' : ($i === $at ? 'current' : 'upcoming')),
            ];
        }
        if ($document->status === 'CANCELLED') {
            $steps[0]['label'] = __(self::T.'steps.voided');
        }

        $facts = [
            ['label' => __(self::T.'col_party'), 'value' => $row['party'] ?? '—'],
            ['label' => __(self::T.'col_reference'), 'value' => $row['reference'] ?? '—'],
            ['label' => __(self::T.'lines_label'), 'value' => $row['lines']],
            ['label' => __(self::T.'col_handler'), 'value' => $row['handler'].($row['mine'] ? ' '.__(self::T.'you') : '')],
            ['label' => __(self::T.'created'), 'value' => __(self::T.'by_at', ['name' => $document->creator?->present()->fullName ?? __('storeops::storeops.system'),
                'at' => $this->moment($document->created_at)])],
        ];
        if ($document->status === 'POSTED' && $document->posted_at) {
            $facts[] = ['label' => __(self::T.'posted_on'), 'value' => __(self::T.'by_at', ['name' => $document->poster?->present()->fullName ?? __('storeops::storeops.system'),
                'at' => $this->moment($document->posted_at)])];
        }

        $checklist = null;
        if (in_array($document->status, ['DRAFT', 'READY'], true)) {
            try {
                $checklist = collect($this->validation->evaluateDocument($document)['checklist'] ?? [])
                    ->map(fn ($check) => ['label' => $check['label'], 'done' => (bool) $check['passed']])->values()->all();
            } catch (\Throwable $e) {
                $checklist = null;
            }
        }

        $timeline = $document->timelines()->with('user')->orderByDesc('created_at')->orderByDesc('id')->limit(8)->get()
            ->map(fn ($event) => [
                'by' => $event->user?->present()->fullName ?? __('storeops::storeops.system'),
                'action' => __(self::T.'timeline.'.strtolower($event->state)),
                'target' => $event->notes ? '— '.$event->notes : '',
                'at' => $this->moment($event->created_at),
            ])->all();

        return $row + compact('steps', 'facts', 'checklist', 'timeline') + [
            'print' => route('storeops.documents.print', ['type' => $document->type, 'id' => $document->id]),
            'takeover' => route('storeops.documents.takeover', ['type' => $document->type, 'id' => $document->id]),
            'void' => route('storeops.documents.void', ['type' => $document->type, 'id' => $document->id]),
            'managed_elsewhere' => $document->status === 'DRAFT' && ! $row['mine'],
        ];
    }

    /** Takeover notices for the signed-in person in the working office, newest first, last 30 days. */
    public function notices(int $locationId, ?int $companyId, int $userId)
    {
        if (! Schema::hasTable('gov_document_notices')) {
            return collect();
        }

        return DB::table('gov_document_notices as notice')
            ->join('gov_documents as document', 'document.id', '=', 'notice.document_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'notice.actor_id')
            ->where('notice.user_id', $userId)->where('document.location_id', $locationId)
            ->when($companyId, fn ($query) => $query->where('document.company_id', $companyId))
            ->where('notice.created_at', '>=', now()->subDays(30))
            ->orderByDesc('notice.id')->limit(5)
            ->get(['document.id', 'document.document_number', 'notice.event_key', 'notice.created_at', 'actor.first_name', 'actor.last_name'])
            ->map(fn ($notice) => [
                'id' => $notice->id,
                'number' => $notice->document_number,
                'event' => $notice->event_key,
                'actor' => trim(($notice->first_name ?? '').' '.($notice->last_name ?? '')) ?: __('storeops::storeops.system'),
                'at' => $this->moment($notice->created_at),
            ]);
    }

    public function typeLabel(string $type): string
    {
        return __(self::T.'types.'.$type);
    }

    private function base(int $locationId): Builder
    {
        return Document::query()->where('gov_documents.location_id', $locationId);
    }

    private function scoped(Builder $query, string $filter, int $userId): Builder
    {
        return match ($filter) {
            'drafts' => $query->where('status', 'DRAFT')->where('managed_by', $userId),
            'ready' => $query->where('status', 'READY'),
            'posted' => $query->where('status', 'POSTED'),
            default => $query,
        };
    }

    private function party(Document $document): ?string
    {
        $t = self::T;

        return match ($document->type) {
            'receipt' => collect([
                $document->purchase_type && Lang::has('storeops::storeops.receiving_sources.'.$document->purchase_type)
                    ? __('storeops::storeops.receiving_sources.'.$document->purchase_type) : $document->purchase_type,
                $document->supplier?->name,
            ])->filter()->implode(' · ') ?: null,
            'issue' => ($name = $document->recipient?->present()->fullName ?: $document->issue_department) ? __($t.'party_to', ['name' => $name]) : null,
            'transfer' => $document->destination?->name ? __($t.'party_to', ['name' => $document->destination->name]) : null,
            'adjustment' => collect([
                $document->adjustment_reason ? __($t.'reasons.'.$document->adjustment_reason) : null,
                $document->source ? __($t.'party_cites', ['number' => $document->source->document_number]) : null,
            ])->filter()->implode(' · ') ?: null,
            'opening' => __($t.'party_opening'),
            default => null,
        };
    }

    private function moment(CarbonInterface|string|null $at): string
    {
        if (! $at) {
            return '—';
        }
        $at = $at instanceof CarbonInterface ? $at : Carbon::parse($at);
        if ($at->isToday()) {
            return __(self::T.'today_at', ['time' => $at->format('H:i')]);
        }
        if ($at->isYesterday()) {
            return __(self::T.'yesterday_at', ['time' => $at->format('H:i')]);
        }

        return $at->translatedFormat($at->isCurrentYear() ? 'j M, H:i' : 'j M Y');
    }
}
