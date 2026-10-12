<?php

namespace GovStore\TenantScope\Services;

use GovStore\Classification\Models\CatalogCollection;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use GovStore\TenantScope\Models\TenantScopeConfig;
use GovStore\TenantScope\Models\TenantScopeMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class NationalChangeReview
{
    public function handle(Request $request, string $ability): ?Response
    {
        if ($request->routeIs('gov.catalog.import.validate')) {
            return null;
        }
        if ($request->filled('review_token')) {
            $request->validate(['change_reason' => 'required|string|min:5|max:1000', 'confirmation' => 'required|in:CHANGE']);
            $token = $request->input('review_token');
            $review = $request->session()->get('gov_national_reviews.'.$token);
            abort_unless($review && $review['user'] === $request->user()->id && $review['target'] === $request->path()
                && $review['method'] === $request->method() && $review['expires'] > time(), 422, __('tenantops::access.validate_first'));
            $reason = $request->input('change_reason');
            $request->replace($review['input'] + ['change_reason' => $reason, 'confirmation' => 'CHANGE']);
            abort_unless($review['fingerprint'] === $this->fingerprint($request), 409, __('tenantops::access.validate_first'));
            // A unique database marker prevents simultaneous consumption of the same review.
            $consumed = DB::table('gov_access_review_uses')->insertOrIgnore(['token_hash' => hash('sha256', $token), 'created_at' => now()]);
            abort_unless($consumed, 409);
            $request->session()->forget('gov_national_reviews.'.$token);

            return null;
        }
        $token = (string) Str::uuid();
        $input = $request->except(['_token', 'change_reason', 'confirmation', 'review_token']);
        $before = $this->currentConfiguration($request);
        $review = ['input' => $input, 'before' => $before, 'user' => $request->user()->id, 'target' => $request->path(), 'method' => $request->method(),
            'expires' => time() + 1800, 'fingerprint' => $this->fingerprint($request)];
        $reviews = $request->session()->get('gov_national_reviews', []);
        $reviews = array_filter($reviews, fn ($entry) => $entry['expires'] > time());
        $reviews[$token] = $review;
        $request->session()->put('gov_national_reviews', array_slice($reviews, -10, null, true));
        $impact = [
            'offices' => DB::table('locations')->whereNull('deleted_at')->count(),
            'categories' => DB::table('categories')->whereNull('deleted_at')->count(),
            'models' => DB::table('models')->whereNull('deleted_at')->count(),
        ];
        $target = url($request->path());
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['action' => __('tenantops::access.national_review'), 'reason' => __('tenantops::access.national_warning'),
                'reason_key' => 'national_review', 'review_url' => route('gov.access.national-review', $token)], 409);
        }

        return response()->view('govscope::access.national-review', compact('token', 'review', 'impact', 'before', 'target', 'ability'));
    }

    public function show(Request $request, string $token)
    {
        $review = $request->session()->get('gov_national_reviews.'.$token);
        abort_unless($review && $review['user'] === $request->user()->id && $review['expires'] > time(), 404);
        $route = app('router')->getRoutes()->match(Request::create(url($review['target']), $review['method']));
        $ability = '';
        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'gov.can:')) {
                $ability = substr($middleware, 8);
            }
        }
        abort_unless($ability && app(GovAccess::class)->decide($request->user(), $ability)->allowed, 403);
        $impact = ['offices' => DB::table('locations')->whereNull('deleted_at')->count(), 'categories' => DB::table('categories')->whereNull('deleted_at')->count(), 'models' => DB::table('models')->whereNull('deleted_at')->count()];
        $before = $review['before'];
        $target = url($review['target']);

        return view('govscope::access.national-review', compact('token', 'review', 'impact', 'before', 'target', 'ability'));
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', json_encode($this->currentConfiguration($request)));
    }

    private function currentConfiguration(Request $request): array
    {
        if ($request->routeIs('gov.membership.override')) {
            $id = $request->input('user_id');

            return ['memberships' => DB::table('gov_office_memberships')->where('user_id', $id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'responsibilities' => DB::table('gov_office_responsibilities')->where('user_id', $id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'grants' => DB::table('gov_access_grants')->where('user_id', $id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'offices' => DB::table('gov_location_profiles')->where('office_admin_id', $id)->orderBy('location_id')->get()->map(fn ($r) => (array) $r)->all()];
        }
        if ($request->routeIs('gov.org.jurisdictions.*')) {
            $query = DB::table('gov_ict_jurisdictions');
            $request->routeIs('gov.org.jurisdictions.destroy')
                ? $query->where('id', $request->route('id')) : $query->where('user_id', $request->input('user_id'));

            return $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        if ($request->routeIs('gov.org.company_admins.*')) {
            $query = DB::table('gov_company_admins');
            $request->routeIs('gov.org.company_admins.destroy')
                ? $query->where('id', $request->route('id')) : $query->where('user_id', $request->input('user_id'));

            return $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        if ($request->routeIs('gov.org.directory.import')) {
            abort_if($request->hasFile('csv_file'), 422);

            return ['bundle_sha256' => hash_file('sha256', base_path('packages/gov-store/organization/src/database/data/bangladesh_ministries_bilingual.csv')),
                'directory' => DB::table('gov_ministries_directory')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
                'companies' => DB::table('companies')->whereNull('deleted_at')->orderBy('id')->get(['id', 'name'])->map(fn ($row) => (array) $row)->all()];
        }
        if ($request->routeIs('gov.scope.save-strategy')) {
            return TenantScopeConfig::orderBy('reference_type')->get()->toArray();
        }
        if ($request->routeIs('gov.scope.mappings.*')) {
            if ($request->routeIs('gov.scope.mappings.destroy')) {
                return TenantScopeMapping::find($request->route('id'))?->toArray() ?? [];
            }

            return TenantScopeMapping::where('reference_type', $request->input('reference_type'))
                ->where('reference_id', $request->input('reference_id'))->orderBy('id')->get()->toArray();
        }
        if ($request->routeIs('storeops.admin.rules.*')) {
            if ($request->routeIs('storeops.admin.rules.unassign')) {
                return ProfileAssignment::with('profile')->find($request->route('id'))?->toArray() ?? [];
            }
            $id = $request->route('id') ?? $request->input('profile_id');
            $profile = $id ? Profile::with('capabilities', 'assignments')->find($id)?->toArray() : null;
            if ($request->filled('target_type')) {
                $targetId = $request->input('target_id') === 'global' ? 1 : (int) $request->input('target_id');

                return ['profile' => $profile, 'target_assignments' => ProfileAssignment::where('target_type', $request->input('target_type'))->where('target_id', $targetId)->get()->toArray()];
            }

            return $profile ?? [];
        }
        if ($request->routeIs('gov.requests.admin.policies.store')) {
            return ApprovalPolicy::where('target_type', $request->input('target_type', 'category'))
                ->where('target_id', $request->input('target_id', $request->input('category_id')))->get()->toArray();
        }
        if ($request->routeIs('gov.catalog.mapping.save')) {
            return (array) DB::table('gov_catalog_snipe_mappings')->where('code', $request->input('code'))->first();
        }
        if (str_contains($request->path(), '/collections/')) {
            $id = $request->route('id') ?? $request->input('collection_id');

            return $id ? CatalogCollection::with('nodes')->find($id)?->toArray() ?? [] : [];
        }

        return [];
    }
}
