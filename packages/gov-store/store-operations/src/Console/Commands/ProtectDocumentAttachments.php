<?php

namespace GovStore\StoreOperations\Console\Commands;

use GovStore\StoreOperations\Models\DocumentAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProtectDocumentAttachments extends Command
{
    protected $signature = 'govstore:protect-attachments';

    protected $description = 'Move existing public store evidence to authenticated private storage';

    public function handle(): int
    {
        $moved = $missing = 0;
        DocumentAttachment::where('disk', 'public')->chunkById(100, function ($attachments) use (&$moved, &$missing) {
            foreach ($attachments as $attachment) {
                $oldPath = $attachment->file_path;
                if (! Storage::disk('public')->exists($oldPath)) {
                    $missing++;

                    continue;
                }
                $privatePath = 'app/gov-store/attachments/'.Str::uuid().'.'.pathinfo($oldPath, PATHINFO_EXTENSION);
                $stream = Storage::disk('public')->readStream($oldPath);
                try {
                    if (! Storage::disk('local')->put($privatePath, $stream)) {
                        throw new \RuntimeException('Private evidence copy failed.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                if (Storage::disk('local')->size($privatePath) !== Storage::disk('public')->size($oldPath)) {
                    throw new \RuntimeException('Private evidence copy size mismatch.');
                }
                DB::transaction(function () use ($privatePath, $oldPath) {
                    // Older imports may have attached the same file to several documents.
                    DocumentAttachment::where('disk', 'public')->where('file_path', $oldPath)
                        ->update(['file_path' => $privatePath, 'disk' => 'local']);
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
                });
                $moved++;
            }
        });
        $this->info("Private evidence files: {$moved}; already missing source files: {$missing}.");

        return self::SUCCESS;
    }
}
