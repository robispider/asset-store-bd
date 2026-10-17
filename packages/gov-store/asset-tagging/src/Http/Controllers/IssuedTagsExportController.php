<?php

namespace GovStore\AssetTagging\Http\Controllers;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Policies\DocumentPolicy;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/** "Download CSV" on a posted GRN's Issued tags box. */
class IssuedTagsExportController extends Controller
{
    public function __invoke(string $document, DocumentPolicy $policy)
    {
        $record = Document::whereKey($document)->firstOrFail();
        $policy->check($record, $record->type);
        $rows = DB::table('gov_asset_registrations as r')
            ->join('gov_document_items as i', 'i.id', '=', 'r.intake_item_id')
            ->leftJoin('models as m', 'm.id', '=', 'i.product_id')
            ->where('i.document_id', $record->id)->whereIn('r.format_version', ['fd18-v1', 'native-v1'])
            ->orderBy('i.id')->orderBy('r.unit_index')
            ->get(['r.asset_tag', 'r.format_version', 'r.gov_code', 'r.serial_number', 'm.name as product']);
        $filename = 'issued-tags-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $record->document_number).'.csv';

        return response()->streamDownload(function () use ($rows, $record) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['document', 'asset_tag', 'kind', 'finance_code', 'product', 'serial']);
            // Free text never starts a spreadsheet formula.
            $safe = fn ($value) => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'".$value : (string) $value;
            foreach ($rows as $row) {
                fputcsv($out, [$record->document_number, $row->asset_tag, $row->format_version === 'fd18-v1' ? 'govt' : 'standard',
                    (string) $row->gov_code, $safe($row->product), $safe($row->serial_number)]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
