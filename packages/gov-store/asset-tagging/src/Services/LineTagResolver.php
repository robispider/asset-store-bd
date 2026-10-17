<?php

namespace GovStore\AssetTagging\Services;

use GovStore\AssetTagging\Formats\FinanceDivisionV1Format;
use GovStore\Classification\Models\FinanceItem;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Works out, per GRN line, which kind of tag its units get and from which facts:
 * Finance source (product type / this line / none), acquisition date (Supplier Challan date, else
 * Purchase Order date — D-09), division and registration location.
 */
class LineTagResolver
{
    public const LINE_ROW = 0; // line-wide choices are saved with the first unit (items[i][meta][0][…])

    public function __construct(
        private OfficeTagFacts $facts,
        private EnablementGate $gate,
        private ProductTypeFinanceDefault $defaults,
        private FinanceDivisionV1Format $format,
    ) {}

    /** Document-level facts shared by every line. */
    public function context(Document $document): array
    {
        $officeId = (int) $document->location_id;
        $enabled = $document->type === 'receipt' && $this->facts->enabled($officeId);
        $references = $document->relationLoaded('references') ? $document->references : $document->references()->get();
        $challan = $references->firstWhere('reference_type', 'Supplier Challan');
        $po = $references->firstWhere('reference_type', 'Purchase Order');
        [$date, $source] = $challan?->reference_date ? [$challan->reference_date, 'challan']
            : ($po?->reference_date ? [$po->reference_date, 'po'] : [null, null]);
        $office = $enabled ? ($this->facts->office($officeId) ?? []) : [];
        $companyId = $this->facts->companyOf($officeId);

        return [
            'office_id' => $officeId,
            'company_id' => $companyId,
            'enabled' => $enabled,
            'paused' => $enabled ? $this->gate->postingFailures($officeId) : [],
            'office' => $office,
            'acquired_on' => $date ? substr((string) $date, 0, 10) : null,
            'date_source' => $source,
            'date_reference' => $source === 'challan' ? $challan?->reference_number : ($source === 'po' ? $po?->reference_number : null),
            'govt_source' => in_array($document->purchase_type ?? 'Purchase', config('asset-tagging.govt_purchase_types', ['Purchase']), true),
        ];
    }

    public function applies(object $item): bool
    {
        return in_array(strtolower(str_replace('_', '', (string) $item->product_type)), ['assetmodel'], true);
    }

    public function categoryOf(object $item): ?int
    {
        $category = DB::table('models')->where('id', $item->product_id)->value('category_id');

        return $category ? (int) $category : null;
    }

    /** Line-wide choices saved with the first unit: gov_code, division_code, tag_choice. */
    public function choices(object $item): array
    {
        // A line that is not saved yet arrives as a plain object with a (possibly empty) metadata collection.
        $rows = $item instanceof \Illuminate\Database\Eloquent\Model
            ? ($item->relationLoaded('metadata') ? $item->metadata : $item->metadata()->get())
            : collect($item->metadata ?? []);
        $first = $rows->where('row_index', self::LINE_ROW);

        return [
            'gov_code' => $first->firstWhere('field_key', 'gov_code')?->value,
            'division_code' => $first->firstWhere('field_key', 'division_code')?->value,
            'tag_choice' => $first->firstWhere('field_key', 'tag_choice')?->value,
            'typed_tags' => $rows->where('field_key', 'asset_tag')->filter(fn ($row) => filled($row->value))->count(),
        ];
    }

    /**
     * The plan for one line. kind: off (office not enabled) · paused · govt · standard · attention.
     * 'warning' marks a missing Finance Code that the storekeeper has not dismissed.
     */
    public function resolve(array $context, object $item): array
    {
        $quantity = max(0, (int) $item->quantity);
        $plan = ['kind' => 'off', 'quantity' => $quantity, 'gov_code' => null, 'item' => null, 'finance_source' => 'none',
            'reason' => null, 'warning' => false, 'attention' => null, 'division_code' => null,
            'location_code' => $context['office']['location_code'] ?? null, 'acquired_on' => $context['acquired_on'],
            'date_source' => $context['date_source'], 'prefix' => null, 'default' => null, 'category_id' => null];
        if (! $context['enabled'] || ! $this->applies($item)) {
            return $plan;
        }
        if ($context['paused']) {
            return ['kind' => 'paused', 'attention' => 'paused'] + $plan;
        }
        $choices = $this->choices($item);
        $categoryId = $this->categoryOf($item);
        $plan['category_id'] = $categoryId;
        $plan['default'] = $categoryId ? $this->defaults->resolve($categoryId) : null;
        $office = $context['office'];
        $plan['division_code'] = in_array($choices['division_code'], $office['division_codes'] ?? [], true)
            ? $choices['division_code'] : ($office['default_division_code'] ?? null);

        if ($choices['typed_tags'] > 0) {
            return ['kind' => 'attention', 'attention' => 'typed_tag'] + $plan;
        }
        if (! $context['govt_source']) {
            return ['kind' => 'standard', 'reason' => 'receiving_source'] + $plan;
        }

        // Finance source: this line's choice, else the product type's default (only when it is not under review).
        if ($choices['gov_code']) {
            if (! $categoryId || $this->defaults->rejection($choices['gov_code'], $categoryId) !== null) {
                return ['kind' => 'attention', 'attention' => 'invalid_code', 'gov_code' => $choices['gov_code']] + $plan;
            }
            $plan = ['gov_code' => $choices['gov_code'], 'finance_source' => 'line_choice'] + $plan;
        } elseif (($plan['default']['status'] ?? null) === 'ok') {
            $plan = ['gov_code' => $plan['default']['gov_code'], 'finance_source' => 'product_type'] + $plan;
        }
        if (! $plan['gov_code']) {
            $chosen = $choices['tag_choice'] === 'standard';

            return ['kind' => 'standard', 'reason' => $chosen ? 'chosen' : 'no_finance_code', 'warning' => ! $chosen] + $plan;
        }
        $plan['item'] = FinanceItem::find($plan['gov_code']);
        if ($choices['tag_choice'] === 'standard') {
            return ['kind' => 'standard', 'reason' => 'chosen'] + $plan;
        }
        if (! $context['acquired_on']) {
            return ['kind' => 'attention', 'attention' => 'date'] + $plan;
        }
        $plan['prefix'] = $this->format->prefix($this->formatFacts($plan));

        return ['kind' => 'govt'] + $plan;
    }

    public function formatFacts(array $plan): array
    {
        return ['gov_code' => $plan['gov_code'], 'acquired_on' => $plan['acquired_on'],
            'location_code' => $plan['location_code'], 'division_code' => $plan['division_code']];
    }
}
