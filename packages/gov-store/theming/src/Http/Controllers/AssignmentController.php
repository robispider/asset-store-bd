<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Models\ThemeAssignment;
use GovStore\Theming\ThemeManager;
use GovStore\Theming\Themes\ThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Organisation / company / office defaults. Administrators only *choose* among shipped themes.
 */
class AssignmentController extends Controller
{
    public function __construct(private ThemeManager $manager, private ThemeAccess $access) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $scopes = $this->access->manageableScopes($user);
        if ($scopes === []) {
            return $this->deny($request, 'theming.assign.office');
        }
        $context = $this->manager->context();
        $any = $this->access->anyScopeId($user);
        $companies = $any ? DB::table('companies')->orderBy('name')->get(['id', 'name']) : collect();
        $offices = $any ? DB::table('locations')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'company_id']) : collect();
        $companyId = $any ? ($request->integer('company_id') ?: $context['company']) : $context['company'];
        $officeId = $any ? ($request->integer('office_id') ?: $context['location']) : $context['location'];
        $officeCompany = $officeId ? DB::table('locations')->where('id', $officeId)->value('company_id') : null;

        $cards = [];
        foreach (['organization' => null, 'company' => $companyId, 'office' => $officeId] as $scope => $id) {
            if (! in_array($scope, $scopes, true)) {
                continue;
            }
            $assignment = ($scope === 'organization' || $id) ? $this->manager->resolver()->assignment($scope, $id) : null;
            $cards[$scope] = [
                'id' => $id,
                'name' => match ($scope) {
                    'organization' => trans('gs-theme::appearance.scope_organization'),
                    'company' => $id ? DB::table('companies')->where('id', $id)->value('name') : null,
                    'office' => $id ? DB::table('locations')->where('id', $id)->value('name') : null,
                },
                'assignment' => $assignment,
                'updated_by' => $assignment && $assignment['updated_by'] ? $this->userName((int) $assignment['updated_by']) : null,
                'effective' => ($scope === 'organization' || $id) ? $this->manager->resolver()->effectiveForScope($scope, $id, $scope === 'office' ? ($officeCompany ? (int) $officeCompany : null) : null) : null,
                'editable' => $scope === 'organization' || ($id && $this->access->canAssign($user, $scope, $id)),
            ];
        }

        return view('gs-theme::appearance.assignments', [
            'cards' => $cards,
            'themes' => $this->manager->repository()->published(),
            'any' => $any,
            'companies' => $companies,
            'offices' => $offices,
        ]);
    }

    public function update(Request $request, string $scope, ?int $id = null)
    {
        $user = $request->user();
        $id = $scope === 'organization' ? null : $id;
        if (($scope !== 'organization' && ! $id) || ! $this->access->canAssign($user, $scope, $id)) {
            return $this->deny($request, ThemeAccess::SCOPE_ABILITIES[$scope] ?? 'theming.assign.office');
        }
        if ($scope !== 'organization') {
            abort_unless(DB::table($scope === 'company' ? 'companies' : 'locations')->where('id', $id)->exists(), 404);
        }
        $data = $request->validate([
            'theme' => ['nullable', 'string', Rule::in(array_keys($this->manager->repository()->published()))],
            'enforced' => ['nullable', 'boolean'],
        ]);
        $query = ThemeAssignment::query()->where('scope_type', $scope);
        $id === null ? $query->whereNull('scope_id') : $query->where('scope_id', $id);
        $existing = $query->first();
        $old = $existing?->only(['theme', 'enforced']);

        if (empty($data['theme'])) {
            $existing?->delete();
        } else {
            $assignment = $existing ?? new ThemeAssignment(['scope_type' => $scope, 'scope_id' => $id]);
            $assignment->fill(['theme' => $data['theme'], 'enforced' => $request->boolean('enforced'), 'updated_by' => $user->id])->save();
        }
        ThemeResolver::forgetAssignment($scope, $id);
        $this->manager->forget();

        Log::info('gs-theme: assignment changed', [
            'actor_id' => $user->id,
            'scope_type' => $scope,
            'scope_id' => $id,
            'old_theme' => $old['theme'] ?? null,
            'new_theme' => $data['theme'] ?? null,
            'old_enforced' => $old['enforced'] ?? null,
            'enforced' => empty($data['theme']) ? null : $request->boolean('enforced'),
        ]);

        return redirect()->route('gs-theme.assignments', array_filter([
            'company_id' => $scope === 'company' ? $id : $request->input('company_id'),
            'office_id' => $scope === 'office' ? $id : $request->input('office_id'),
        ]))->with('success', trans('gs-theme::appearance.assignment_saved'));
    }

    private function userName(int $id): ?string
    {
        $row = DB::table('users')->where('id', $id)->first(['first_name', 'last_name']);

        return $row ? trim($row->first_name.' '.$row->last_name) : null;
    }
}
