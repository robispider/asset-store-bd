<?php

namespace GovStore\AssetTagging\Http\Controllers;

use App\Models\Asset;
use GovStore\AssetTagging\Services\TagDecoder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * S7 · tag lookup. A tag never grants access: "not found" and "not yours" give the same neutral answer,
 * and the decoded segments are shown only when the user may view the asset.
 */
class TagLookupController extends Controller
{
    public function __invoke(Request $request, TagDecoder $decoder)
    {
        $data = $request->validate(['tag' => 'nullable|string|max:255']);
        $tag = trim((string) ($data['tag'] ?? ''));
        $result = null;
        if ($tag !== '') {
            // Office scoping (MinistryLocationScope) and the asset policy both apply.
            $asset = Asset::with(['model', 'location', 'status'])->where('asset_tag', $tag)->first();
            if ($asset && Gate::allows('view', $asset)) {
                $registration = DB::table('gov_asset_registrations as r')
                    ->leftJoin('gov_document_items as i', 'i.id', '=', 'r.intake_item_id')
                    ->leftJoin('gov_documents as d', 'd.id', '=', 'i.document_id')
                    ->where('r.asset_id', $asset->id)->first(['r.format_version', 'r.gov_code', 'r.tag_snapshot', 'r.created_at', 'd.document_number', 'd.id as document_id']);
                $result = ['asset' => $asset, 'decoded' => $decoder->decode($tag), 'registration' => $registration,
                    'snapshot' => $registration?->tag_snapshot ? json_decode($registration->tag_snapshot, true) : null];
            }
        }

        return view('asset-tagging::lookup.index', ['tag' => $tag, 'result' => $result, 'searched' => $tag !== '']);
    }
}
