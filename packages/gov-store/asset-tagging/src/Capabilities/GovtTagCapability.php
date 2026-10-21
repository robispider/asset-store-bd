<?php

namespace GovStore\AssetTagging\Capabilities;

use GovStore\AssetTagging\Services\LineTagResolver;
use GovStore\StoreOperations\Contracts\CapabilityInterface;
use GovStore\StoreOperations\Contracts\ContributesChecklist;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Support\Facades\Validator;

/**
 * issue_govt_tags: the GRN line's tag panel (renderUI), its checklist rows and input validation.
 * Issuance itself runs through store-operations' AssetTagIssuer, so execute() does nothing,
 * like RequireSerialCapability. In an office that is not enabled the capability adds nothing.
 */
class GovtTagCapability implements CapabilityInterface, ContributesChecklist
{
    public const CODE = 'issue_govt_tags';

    public function __construct(private LineTagResolver $resolver) {}

    public function getRequirements(array $config = []): array
    {
        return [];
    }

    public function validate(array $data, array $config = []): array
    {
        $validator = Validator::make($data, [
            'meta.'.LineTagResolver::LINE_ROW.'.gov_code' => 'nullable|digits:6',
            'meta.'.LineTagResolver::LINE_ROW.'.division_code' => 'nullable|digits:2',
            'meta.'.LineTagResolver::LINE_ROW.'.tag_choice' => 'nullable|in:standard',
        ]);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    public function execute(object $item, array $config = []): void
    {
        // Tags are issued by AssetTagIssuer inside CreateAssetsCapability.
    }

    public function renderUI(?object $item = null, array $config = []): string
    {
        // Rendered by the workspace's renderMeta endpoint, which has already authorized the document.
        $document = Document::find(request()->input('document_id'));
        if (! $document || $document->type !== 'receipt' || $document->status === 'POSTED') {
            return '';
        }
        $context = $this->resolver->context($document);
        $line = $item ?? (object) ['product_type' => strtolower(class_basename((string) request()->input('product_type'))),
            'product_id' => (int) request()->input('product_id'), 'quantity' => 1, 'metadata' => collect(), 'product_name' => null];
        $line->quantity = (int) ($config['quantity'] ?? $line->quantity ?? 1);
        if (! $context['enabled'] || ! $this->resolver->applies($line)) {
            return '';
        }

        return view('asset-tagging::grn.line-panel', [
            'plan' => $this->resolver->resolve($context, $line),
            'context' => $context,
            'choices' => $this->resolver->choices($line),
            'index' => (int) ($config['row_index'] ?? 0),
            'editable' => $document->status === 'DRAFT',
        ])->render();
    }

    public function checklist(Document $document, object $item, array $config = []): array
    {
        $context = $this->resolver->context($document);
        if (! $context['enabled'] || ! $this->resolver->applies($item)) {
            return [];
        }
        $plan = $this->resolver->resolve($context, $item);
        $name = $item->product_name ?? '';

        return match (true) {
            $plan['kind'] === 'attention' => [['label' => __('asset-tagging::tagging.checklist.'.$plan['attention'], ['item' => $name]), 'passed' => false, 'level' => 'required']],
            $plan['kind'] === 'paused' => [['label' => __('asset-tagging::tagging.checklist.paused', ['item' => $name]), 'passed' => false, 'level' => 'required']],
            $plan['warning'] => [['label' => __('asset-tagging::tagging.checklist.no_finance', ['item' => $name, 'count' => $plan['quantity']]), 'passed' => true, 'level' => 'warning']],
            default => [],
        };
    }
}
