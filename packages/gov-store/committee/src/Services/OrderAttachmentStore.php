<?php

namespace GovStore\Committee\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderAttachmentStore
{
    public function store(UploadedFile $file): array
    {
        $extensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        $mime = $file->getMimeType();
        if (! isset($extensions[$mime]) || $file->getSize() > config('committee.attachment_max_kb',10240)*1024 || ! $file->isValid()) {
            throw ValidationException::withMessages(['file'=>__('committee::committee.invalid_file')]);
        }
        $path = 'orders/'.Str::uuid().'.'.$extensions[$mime];
        Storage::disk('committee_private')->put($path,file_get_contents($file->getRealPath()));
        return ['attachment_disk'=>'committee_private','attachment_path'=>$path,'attachment_sha256'=>hash_file('sha256',$file->getRealPath()),'attachment_mime'=>$mime,'attachment_size'=>$file->getSize()];
    }
    public function stream(string $path, string $hash)
    {
        $disk = Storage::disk('committee_private');
        abort_unless(str_starts_with($path,'orders/') && ! str_contains($path,'..') && $disk->exists($path),404);
        abort_unless(hash_equals($hash,hash('sha256',$disk->get($path))),409);
        return $disk->download($path,basename($path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
}
