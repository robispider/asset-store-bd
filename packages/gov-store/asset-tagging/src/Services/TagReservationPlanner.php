<?php

namespace GovStore\AssetTagging\Services;

use GovStore\AssetTagging\Formats\FinanceDivisionV1Format;
use GovStore\Classification\Models\FinanceItem;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Validation\ValidationException;

/**
 * Builds and locks the whole posting's reservations, in the architecture's lock order:
 * office/company metadata FOR SHARE → plan → Finance items FOR SHARE → Govt. Tag counters (sorted)
 * → settings row (standard tags). Runs inside the posting transaction; locks are held until commit.
 */
class TagReservationPlanner
{
    public function __construct(
        private LineTagResolver $resolver,
        private MetadataValueStore $store,
        private GovtTagSequenceAllocator $govt,
        private NativeSequenceAllocator $native,
        private FinanceDivisionV1Format $format,
    ) {}

    /** @return array<string, array<int, array>> item id => unit index => issued tag */
    public function reserve(Document $document): array
    {
        // Offices that are not enabled keep today's behaviour and never touch the tagging tables.
        if (! app(OfficeTagFacts::class)->enabled((int) $document->location_id)) {
            return [];
        }
        $revisions = $this->lockFacts($document);
        $context = $this->resolver->context($document);
        if (! $context['enabled']) {
            return [];
        }
        if ($context['paused']) {
            throw ValidationException::withMessages(['items' => __('asset-tagging::tagging.errors.paused')]);
        }
        $items = $document->items()->with('metadata')->get();
        $plans = [];
        foreach ($items as $item) {
            $plan = $this->resolver->resolve($context, $item);
            if ($plan['kind'] === 'attention') {
                throw ValidationException::withMessages(['items' => __('asset-tagging::tagging.attention.'.$plan['attention'], ['item' => $item->product_name])]);
            }
            if ($plan['default'] && $plan['category_id']) {
                $revisions['default:'.$plan['category_id']] = $plan['default']['revision'];
            }
            $plans[$item->id] = $plan;
        }

        // Finance items used by this posting must still be active when the counters are taken.
        $codes = collect($plans)->where('kind', 'govt')->pluck('gov_code')->unique()->values()->all();
        if ($codes) {
            $active = FinanceItem::whereIn('gov_code', $codes)->where('status', 'active')->sharedLock()->pluck('gov_code')->all();
            if (array_diff($codes, $active)) {
                throw ValidationException::withMessages(['items' => __('asset-tagging::tagging.errors.code_changed')]);
            }
        }

        $needs = [];
        foreach ($plans as $plan) {
            if ($plan['kind'] === 'govt' && $plan['quantity'] > 0) {
                $needs[$plan['prefix']] = ($needs[$plan['prefix']] ?? 0) + $plan['quantity'];
            }
        }
        $next = $this->govt->reserve($needs);
        $standardCount = collect($plans)->where('kind', 'standard')->sum('quantity');
        $standard = $this->native->reserve((int) $standardCount);

        $reserved = [];
        foreach ($items as $item) {
            $plan = $plans[$item->id];
            if (! in_array($plan['kind'], ['govt', 'standard'], true)) {
                continue;
            }
            for ($unit = 0; $unit < $plan['quantity']; $unit++) {
                if ($plan['kind'] === 'govt') {
                    $sequence = $next[$plan['prefix']]++;
                    $tag = $this->format->encode($this->resolver->formatFacts($plan), $sequence);
                    $reserved[$item->id][$unit] = $this->issued($tag, FinanceDivisionV1Format::VERSION, $plan, $context, $revisions, $document,
                        $this->format->decode($tag));
                } else {
                    $reserved[$item->id][$unit] = $this->issued(array_shift($standard), 'native-v1', $plan, $context, $revisions, $document, null);
                }
            }
        }

        return $reserved;
    }

    /** Office facts, enablement and company lists are read under a shared lock and re-checked. */
    private function lockFacts(Document $document): array
    {
        $officeId = (int) $document->location_id;
        $revisions = [];
        foreach ([OfficeTagFacts::ENABLEMENT, OfficeTagFacts::OFFICE] as $namespace) {
            $revisions[$namespace] = $this->store->shared('office', $officeId, $namespace)?->revision;
        }
        $companyId = (int) app(OfficeTagFacts::class)->companyOf($officeId);
        $revisions[OfficeTagFacts::CODELISTS] = $companyId ? $this->store->shared('company', $companyId, OfficeTagFacts::CODELISTS)?->revision : null;

        return $revisions;
    }

    private function issued(string $tag, string $version, array $plan, array $context, array $revisions, Document $document, ?array $segments): array
    {
        $govt = $version === FinanceDivisionV1Format::VERSION;

        return [
            'tag' => $tag,
            'format_version' => $version,
            'gov_code' => $govt ? $plan['gov_code'] : null,
            'purchase_date' => $govt ? $plan['acquired_on'] : null,
            'snapshot' => array_filter([
                'segments' => $segments,
                'acquired_on' => $govt ? $plan['acquired_on'] : null,
                'date_source' => $govt ? $plan['date_source'] : null,
                'date_reference' => $govt ? $context['date_reference'] : null,
                'office_id' => $context['office_id'],
                'company_id' => $context['company_id'],
                'location_code' => $govt ? $plan['location_code'] : null,
                'division_code' => $govt ? $plan['division_code'] : null,
                'revisions' => $revisions,
                'finance_source' => $plan['finance_source'],
                'standard_reason' => $govt ? null : $plan['reason'],
                'document' => $document->document_number,
                'actor' => auth()->id() ?? $document->posted_by,
            ], fn ($value) => $value !== null),
        ];
    }
}
