<?php

namespace GovStore\Classification\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogReview
{
    public function create(Request $request, array $paths, string $scheme, string $version): string
    {
        $token = (string) Str::uuid();
        $request->session()->put('gov_catalog_review', ['token' => $token, 'user' => $request->user()->id,
            'scheme' => $scheme, 'version' => $version, 'fingerprint' => $this->fingerprint($paths), 'expires' => time() + 1800]);

        return $token;
    }

    public function consume(Request $request, array $paths): void
    {
        $review = $request->session()->get('gov_catalog_review');
        abort_unless($review && hash_equals($review['token'], (string) $request->input('catalog_review_token'))
            && $review['user'] === $request->user()->id && $review['scheme'] === $request->input('scheme')
            && $review['version'] === $request->input('version') && $review['expires'] > time()
            && $review['fingerprint'] === $this->fingerprint($paths), 422, __('tenantops::access.validate_first'));
        $consumed = DB::table('gov_access_review_uses')->insertOrIgnore([
            'token_hash' => hash('sha256', 'catalog|'.$review['token']), 'created_at' => now(),
        ]);
        abort_unless($consumed, 409);
        $request->session()->forget('gov_catalog_review');
    }

    private function fingerprint(array $paths): array
    {
        return array_map(fn ($path) => hash_file('sha256', $path), $paths);
    }
}
