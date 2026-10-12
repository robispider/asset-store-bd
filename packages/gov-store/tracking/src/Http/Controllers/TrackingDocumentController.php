<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingDocument;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrackingDocumentController extends Controller
{
    public function __construct(private TrackingAuthorizationService $authorization) {}

    private function authorizeDocument(Initiative $initiative, TrackingCode $task, ?TrackingDocument $document = null, bool $write = false): void
    {
        abort_unless((int) $task->initiative_id === (int) $initiative->id
            && (! $document || (int) $document->tracking_code_id === (int) $task->id), 404);
        $this->authorization->authorize($initiative, $write ? ['HEAD', 'OFFICER'] : ['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR']);
        if ($write) {
            abort_unless($initiative->status === 'Planning' && $task->status === 'DRAFT'
                || $initiative->status === 'Active' && $task->status === 'DRAFT', 409);
        }
    }

    public function store(Request $request, Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authorizeDocument($initiative, $trackingCode, write: true);
        $request->validate(['document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|max:10240']);
        $file = $request->file('document');
        $path = $file->store('tracking-documents/'.$trackingCode->id, 'local');
        try {
            DB::transaction(function () use ($initiative, $trackingCode, $file, $path) {
                $task = TrackingCode::whereKey($trackingCode->id)->lockForUpdate()->firstOrFail();
                $parent = Initiative::whereKey($initiative->id)->lockForUpdate()->firstOrFail();
                $this->authorizeDocument($parent, $task, write: true);
                $task->documents()->create([
                    'file_name' => basename($file->getClientOriginalName()), 'file_path' => $path,
                    'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType(), 'uploaded_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        return redirect()->route('gov.tracking.initiatives.tracking-codes.edit', [$initiative, $trackingCode])
            ->with('success', __('govtracking::general.document_uploaded'));
    }

    public function download(Initiative $initiative, TrackingCode $trackingCode, TrackingDocument $document)
    {
        $this->authorizeDocument($initiative, $trackingCode, $document);
        abort_unless(str_starts_with($document->file_path, 'tracking-documents/'.$trackingCode->id.'/')
            && ! str_contains($document->file_path, '..') && Storage::disk('local')->exists($document->file_path), 404);
        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function destroy(Initiative $initiative, TrackingCode $trackingCode, TrackingDocument $document)
    {
        DB::transaction(function () use ($initiative, $trackingCode, $document) {
            $task = TrackingCode::whereKey($trackingCode->id)->lockForUpdate()->firstOrFail();
            $parent = Initiative::whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            $document = TrackingDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->authorizeDocument($parent, $task, $document, true);
            abort_unless(str_starts_with($document->file_path, 'tracking-documents/'.$task->id.'/')
                && ! str_contains($document->file_path, '..'), 404);
            $path = $document->file_path;
            $document->delete();
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
        return redirect()->route('gov.tracking.initiatives.tracking-codes.edit', [$initiative, $trackingCode])
            ->with('success', __('govtracking::general.document_deleted'));
    }
}
