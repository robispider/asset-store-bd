<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\FinanceItem;
use GovStore\Metadata\Contracts\MetadataValueSchema;
use GovStore\Metadata\Services\MetadataValueStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The optional Finance Code of a product type (category), stored as typed metadata. Ownership follows
 * explicit national/company/office ownership. Adoption never creates an override.
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
        $category = new \App\Models\Category;
        $category->id = $subjectId;
        [$type, $id] = app(\GovStore\TenantScope\Services\ReferenceOwnershipService::class)->owner($category);

        return [$type, $id ?? 0];
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

    /** The definition's owning scope sets its default; national edits require national review. */
    public function canEdit($user, int $categoryId): bool
    {
        if (! $user) {
            return false;
        }
        [$scopeType, $scopeId] = $this->scope('category', $categoryId);
        $access = app(\GovStore\TenantScope\Services\GovAccess::class);
        if ($scopeType === 'national') {
            return $access->decide($user, 'catalog.master.manage')->allowed;
        }
        $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
        if ($scopeType === 'unresolved') {
            return false;
        }
        if ($scopeType === 'office') {
            return $context->locationId === $scopeId && $access->decide($user, 'catalog.office.adopt')->allowed;
        }

        return $user->isSuperUser() || ((int) $context->companyId === $scopeId && in_array('company_admin', $access->roles($user), true));
    }
}
