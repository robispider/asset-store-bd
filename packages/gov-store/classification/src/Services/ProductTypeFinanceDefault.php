<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\FinanceItem;
use GovStore\Metadata\Contracts\MetadataValueSchema;
use GovStore\Metadata\Services\MetadataValueStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The optional Finance Code of a product type (category), stored as typed metadata. Ownership follows
 * category governance: global (or ungoverned) types are national, company types belong to their company.
 * Offices never override it.
 */
class ProductTypeFinanceDefault implements MetadataValueSchema
{
    public const NAMESPACE = 'asset-tagging.default';

    public function __construct(private ProductTypeCommodity $commodity) {}

    public function subjectTypes(): array
    {
        return ['category'];
    }

    public function scope(string $subjectType, int $subjectId): array
    {
        $governance = DB::table('gov_category_governance')->where('category_id', $subjectId)->first();

        return $governance && $governance->governance_type === 'company' && $governance->created_by_company_id
            ? ['company', (int) $governance->created_by_company_id] : ['national', 0];
    }

    public function normalize(string $subjectType, int $subjectId, array $payload, ?array $current): array
    {
        abort_unless(DB::table('categories')->where('id', $subjectId)->whereNull('deleted_at')->exists(), 404);
        $code = $payload['gov_code'] ?? null;
        if ($code === null || $code === '') {
            return ['gov_code' => null];
        }
        $reason = $this->rejection((string) $code, $subjectId);
        if ($reason) {
            throw ValidationException::withMessages(['gov_code' => $reason]);
        }

        return ['gov_code' => (string) $code];
    }

    /** Null when the code can be the product type's default, otherwise the reason it cannot. */
    public function rejection(string $code, int $categoryId): ?string
    {
        $item = FinanceItem::find($code);
        if (! $item || $item->status !== 'active') {
            return __('classification::finance.code_not_active');
        }
        if ($item->unspsc_code !== null && $item->unspsc_code !== $this->commodity->codeFor($categoryId)) {
            return __('classification::finance.code_other_unspsc');
        }

        return null;
    }

    /**
     * The effective default: ['gov_code', 'item', 'status' => none|ok|needs_review, 'scope', 'revision'].
     * The owner scope and the item are re-checked on every read, so a change of commodity or ownership
     * shows as needs_review (GRN ignores it) without the stored value ever being remapped.
     */
    public function resolve(int $categoryId): array
    {
        $row = app(MetadataValueStore::class)->find('category', $categoryId, self::NAMESPACE);
        $scope = $this->scope('category', $categoryId);
        $code = $row?->payload['gov_code'] ?? null;
        $base = ['gov_code' => $code, 'item' => null, 'scope' => $scope, 'revision' => $row?->revision, 'status' => 'none'];
        if (! $code) {
            return $base;
        }
        $base['item'] = FinanceItem::find($code);
        $stale = [$row->scope_type, (int) $row->scope_id] !== $scope || $this->rejection($code, $categoryId) !== null;

        return ['status' => $stale ? 'needs_review' : 'ok'] + $base;
    }

    /** Can this user edit the default? National publisher for national types; the owning company admin otherwise. */
    public function canEdit($user, int $categoryId): bool
    {
        [$scopeType, $scopeId] = $this->scope('category', $categoryId);
        $access = app(\GovStore\TenantScope\Services\GovAccess::class);
        if ($scopeType === 'national') {
            return $access->decide($user, 'catalog.master.manage')->allowed;
        }
        $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);

        return $user->isSuperUser() || ((int) $context->companyId === $scopeId && in_array('company_admin', $access->roles($user), true));
    }
}
