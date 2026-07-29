<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\TrackingDocument;
use GovStore\Tracking\Models\TrackingReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TrackingDocumentController extends Controller
{
    public function store(Request $request, TrackingReference $reference)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|max:10240',
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        
        // Save file to private local disk
        $path = $file->store('tracking-documents/' . $reference->id, 'local');

        TrackingDocument::create([
            'tracking_reference_id' => $reference->id,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()
            ->route('gov.tracking.references.show', $reference->id)
            ->with('success', 'Document uploaded successfully.');
    }

    public function download(TrackingDocument $document)
    {
        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found on storage.');
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function destroy(TrackingDocument $document)
    {
        $referenceId = $document->tracking_reference_id;
        $document->delete();

        return redirect()
            ->route('gov.tracking.references.show', $referenceId)
            ->with('success', 'Document deleted successfully.');
    }
}
