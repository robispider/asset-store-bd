<?php

namespace GovStore\AssetTagging\Services;

use App\Models\Asset;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The asset-tagging side of store-operations' AssetTagIssuer. Offices that are not enabled keep
 * today's behaviour exactly (typed tag, else Snipe-IT's auto-increment, recorded as entered-v1).
 */
class TagIssuanceService
{
    /** @var array<string, array<string, array<int, array>>> document id => item id => unit => tag */
    private array $reserved = [];

    public function __construct(private TagReservationPlanner $planner, private LineTagResolver $resolver) {}

    public function prepare(Document $document): void
    {
        unset($this->reserved[$document->id]);
        if ($document->type !== 'receipt') {
            return;
        }
        $reserved = $this->planner->reserve($document);
        if ($reserved) {
            $this->reserved[$document->id] = $reserved;
        }
    }

    public function tagFor(object $item, int $unitIndex, ?string $enteredTag): array
    {
        $reserved = $this->reserved[$item->document_id] ?? null;
        if ($reserved === null) {
            // Not an enabled office: exactly today's behaviour.
            $tag = $enteredTag ?: Asset::autoincrement_asset();

            return ['tag' => (string) $tag, 'format_version' => 'entered-v1', 'gov_code' => null, 'purchase_date' => null,
                'snapshot' => ['source' => $enteredTag ? 'typed' : 'native_autoincrement', 'actor' => auth()->id()]];
        }
        $issued = $reserved[$item->id][$unitIndex] ?? null;
        if (! $issued) {
            throw new RuntimeException('No asset tag was reserved for this unit; the posting is rolled back.');
        }

        return $issued;
    }

    /** Is this tag reserved by a posting in progress? (ManagedTagGuard lets only these 18-digit tags through.) */
    public function isReserved(string $tag): bool
    {
        foreach ($this->reserved as $items) {
            foreach ($items as $units) {
                foreach ($units as $issued) {
                    if ($issued['tag'] === $tag) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** Counts for the posting modal, or null when the office is not enabled. */
    public function summary(Document $document): ?array
    {
        $context = $this->resolver->context($document);
        if (! $context['enabled']) {
            return null;
        }
        $counts = ['govt' => 0, 'standard' => 0, 'attention' => 0];
        foreach ($document->items()->with('metadata')->get() as $item) {
            $plan = $this->resolver->resolve($context, $item);
            if (isset($counts[$plan['kind']])) {
                $counts[$plan['kind']] += $plan['kind'] === 'attention' ? 1 : $plan['quantity'];
            }
        }

        return $counts + ['line' => self::line($counts['govt'], $counts['standard'])];
    }

    /** "Tags: 2 Govt. Tags · 1 standard tag — permanent" (posting modal and posted panel). */
    public static function line(int $govt, int $standard): string
    {
        return __('asset-tagging::tagging.modal.tags', [
            'govt' => trans_choice('asset-tagging::tagging.panel.count_govt', $govt, ['count' => $govt]),
            'standard' => trans_choice('asset-tagging::tagging.panel.count_standard', $standard, ['count' => $standard]),
        ]);
    }

    /** The posted workspace's "Issued tags" box (empty when nothing was issued by asset tagging). */
    public function postedPanel(Document $document): string
    {
        $rows = DB::table('gov_asset_registrations as r')
            ->join('gov_document_items as i', 'i.id', '=', 'r.intake_item_id')
            ->leftJoin('models as m', 'm.id', '=', 'i.product_id')
            ->where('i.document_id', $document->id)->whereIn('r.format_version', ['fd18-v1', 'native-v1'])
            ->orderBy('i.id')->orderBy('r.unit_index')
            ->get(['r.asset_id', 'r.asset_tag', 'r.format_version', 'r.serial_number', 'r.gov_code', 'm.name as product']);
        if ($rows->isEmpty()) {
            return '';
        }

        return view('asset-tagging::grn.posted-panel', ['document' => $document, 'rows' => $rows])->render();
    }
}
