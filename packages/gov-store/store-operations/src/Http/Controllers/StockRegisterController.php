<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Models\AssetModel;
use GovStore\StoreOperations\DTOs\LedgerPeriod;
use GovStore\StoreOperations\Services\AssetUnitRegister;
use GovStore\StoreOperations\Services\InventoryLedgerService;
use GovStore\StoreOperations\Services\StockCardBuilder;
use GovStore\StoreOperations\Services\StockLedgerReader;
use GovStore\StoreOperations\Services\StockRegisterQuery;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The store's read-only books: the register, stock cards, asset cards and their printed copies. */
class StockRegisterController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(
        protected InventoryLedgerService $ledgerService,
        protected AssetUnitRegister $assetUnits,
        protected StockLedgerReader $ledger,
        protected StockRegisterQuery $register,
        protected StockCardBuilder $cards,
    ) {}

    public function index(Request $request)
    {
        $office = $this->office();
        $filters = $this->registerFilters($request);
        $rows = $office['id'] ? $this->register->rows($office['id']) : collect();
        $counts = $this->register->counts($rows);
        $shown = $this->register->filter($rows, $filters);
        $page = max(1, (int) $request->query('page', 1));
        $items = new LengthAwarePaginator($shown->forPage($page, self::PER_PAGE)->values(), $shown->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => StockRegisterQuery::query($filters)]);
        $abilities = $this->abilities();
        $opened = $office['id'] && $this->ledger->isOpened($office['id']);

        return view('storeops::register.index', compact('office', 'filters', 'counts', 'items', 'abilities', 'opened'));
    }

    public function export(Request $request): StreamedResponse
    {
        $office = $this->office();
        abort_unless($office['id'], 404);
        $rows = $this->register->filter($this->register->rows($office['id']), $this->registerFilters($request));
        $t = fn ($key) => __('storeops::storeops.stock_register.'.$key);

        return $this->csv('stock-register-'.now()->format('Ymd-His').'.csv',
            [$t('col_item'), $t('col_code'), $t('col_type'), $t('col_category'), $t('col_balance'), $t('reorder_level'), $t('col_status'), $t('col_last_date'), $t('col_last_document')],
            $rows->map(fn ($r) => [$r['name'], $r['code'], __('storeops::storeops.stock_register.types.'.$r['type']), $r['category'],
                $r['balance'], $r['reorder'], __('storeops::storeops.stock_register.statuses.'.$r['status']),
                $r['last_at'] ? \Carbon\Carbon::parse($r['last_at'])->toDateString() : null, $r['last_document']]));
    }

    /**
     * Stock card (Kardex) for one item. AJAX callers (the native Snipe-IT item tab) keep the compact table.
     */
    public function kardex(Request $request, $type, $id)
    {
        [$type, $item] = $this->item($type, (int) $id);

        if ($request->ajax()) {
            $movements = $this->office()['id']
                ? $this->ledgerService->getKardexFor(get_class($item), (int) $item->id, 100, $this->office()['id'])
                : collect();

            return view('storeops::register.kardex-table', compact('movements'));
        }

        $office = $this->office();
        $facts = $this->facts($type, $item);
        $card = $office['id'] ? $this->card($request, $type, $item, $office['id']) : null;
        $abilities = $this->abilities();

        if ($type === 'assetmodel') {
            $units = $office['id'] ? $this->assetUnits->units((int) $item->id, $office['id']) : collect();
            $unitFilters = ['status' => in_array($request->query('unit_status'), ['all', 'store', 'issued', 'other'], true) ? $request->query('unit_status') : 'all',
                'q' => trim((string) $request->query('unit_q', ''))];

            return view('storeops::register.asset-card', compact('type', 'item', 'office', 'facts', 'card', 'abilities', 'units', 'unitFilters'));
        }

        return view('storeops::register.kardex', compact('type', 'item', 'office', 'facts', 'card', 'abilities'));
    }

    public function exportCard(Request $request, $type, $id): StreamedResponse
    {
        [$type, $item] = $this->item($type, (int) $id);
        $office = $this->office();
        abort_unless($office['id'], 404);
        $card = $this->card($request, $type, $item, $office['id']);
        $t = fn ($key) => __('storeops::storeops.stock_card.'.$key);
        $rows = collect([[$card['period']->start->toDateString(), null, $t('brought_forward'), null, null, null, $card['bf'], null]])
            ->merge($card['period_entries']->map(fn ($e) => [$e['at']->format('Y-m-d H:i'), $e['document_number'],
                __('storeops::storeops.stock_card.kinds.'.$e['kind']).($e['party'] ? ': '.$e['party'] : ''), implode(', ', $e['tags']),
                $e['direction'] === 'IN' ? $e['qty'] : null, $e['direction'] === 'OUT' ? $e['qty'] : null, $e['balance'], $e['poster']['name'] ?? null]))
            ->push([$card['period']->end->toDateString(), null, $t('period_totals'), null, $card['in'], $card['out'], $card['closing'], null]);

        return $this->csv('stock-card-'.$type.'-'.$item->id.'-'.now()->format('Ymd').'.csv',
            [$t('col_date'), $t('col_document'), $t('col_particulars'), $t('col_tags'), $t('col_in'), $t('col_out'), $t('col_balance'), $t('col_posted_by')], $rows);
    }

    /** A4 audit copy with reconciliation statement, fingerprint and signature blocks. */
    public function printCard(Request $request, $type, $id)
    {
        [$type, $item] = $this->item($type, (int) $id);
        $office = $this->office();
        abort_unless($office['id'], 404);
        $facts = $this->facts($type, $item);
        $card = $this->card($request, $type, $item, $office['id']);
        $printedBy = auth()->user();

        return view('storeops::register.print', compact('type', 'item', 'office', 'facts', 'card', 'printedBy'));
    }

    private function card(Request $request, string $type, Model $item, int $locationId): array
    {
        $tags = $type === 'assetmodel'
            ? $this->assetUnits->units((int) $item->id, $locationId)->groupBy('document_id')->map(fn ($rows) => $rows->pluck('tag')->all())->all()
            : [];

        return $this->cards->build($type, (int) $item->id, $locationId, $this->reorder($item), LedgerPeriod::fromRequest($request),
            ['kind' => $request->query('kind'), 'q' => $request->query('q'), 'entry' => $request->query('entry'),
                'page' => $request->query('page'), 'path' => $request->url()], $tags);
    }

    /** @return array{0: string, 1: Model} */
    private function item(string $type, int $id): array
    {
        $type = strtolower($type);
        $class = StockLedgerReader::TYPES[$type] ?? abort(404, __('storeops::storeops.invalid_stockable_type'));
        $item = $class::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        $office = $this->office()['id'];

        // Office stock belongs to its office; asset models are shared and scoped by the ledger instead.
        if ($class !== AssetModel::class && $office && (int) $item->location_id !== $office
            && ! $this->ledger->movements($type, $id, $office)->isNotEmpty()) {
            abort(404);
        }

        return [$type, $item];
    }

    private function facts(string $type, Model $item): array
    {
        return [
            'type' => $type,
            'code' => $item->item_no ?: ($item->model_number ?: null),
            'category' => $item->category->name ?? null,
            'manufacturer' => $type === 'assetmodel' ? ($item->manufacturer->name ?? null) : null,
            'reorder' => $this->reorder($item),
            'unit' => $type === 'assetmodel' ? __('storeops::storeops.asset_register.units_word') : null,
        ];
    }

    private function reorder(Model $item): ?int
    {
        return (int) $item->min_amt > 0 ? (int) $item->min_amt : null;
    }

    /** Working office, its company and ledger opening, for the context bar and printed header. */
    private function office(): array
    {
        $context = app(TenantContext::class);
        $id = $context->locationId ? (int) $context->locationId : null;
        $location = $id && Schema::hasTable('locations') ? DB::table('locations')->where('id', $id)->first() : null;
        $companyId = $location->company_id ?? $context->companyId;
        $company = $companyId && Schema::hasTable('companies') ? DB::table('companies')->where('id', $companyId)->value('name') : null;

        return [
            'id' => $id,
            'name' => $location->name ?? null,
            'company' => $company,
            'address' => $location ? collect([$location->address ?? null, $location->address2 ?? null, $location->city ?? null])->filter()->implode(', ') : null,
            'opened_at' => $id ? $this->ledger->openedAt($id) : null,
        ];
    }

    private function abilities(): array
    {
        $user = auth()->user();
        if (! $user) {
            return ['draft' => false, 'post' => false];
        }
        $access = app(GovAccess::class);

        return [
            'draft' => $access->decide($user, 'storeops.documents.draft')->allowed,
            'post' => $access->decide($user, 'storeops.documents.post')->allowed,
        ];
    }

    private function registerFilters(Request $request): array
    {
        $type = $request->query('type');
        $status = $request->query('status');
        $sort = $request->query('sort');

        return [
            'q' => trim((string) $request->query('q', '')),
            'type' => array_key_exists((string) $type, StockLedgerReader::TYPES) ? $type : 'all',
            'status' => in_array($status, StockRegisterQuery::STATUSES, true) ? $status : 'all',
            'sort' => in_array($sort, StockRegisterQuery::SORTS, true) ? $sort : 'attention',
        ];
    }

    private function csv(string $filename, array $header, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                // Neutralise spreadsheet formulas in free text.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
