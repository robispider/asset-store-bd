<?php
namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Services\CategoryGovernanceService;

class CategoryGovernanceController extends Controller
{
    private function checkAccess()
    {
        $user = auth()->user();
        if (!$user || (!$user->isSuperUser() && !$user->hasAccess('admin'))) {
            abort(403, __('classification::texts.ctrl_exception_unauthorized_governance'));
        }
    }

    public function index(CategoryGovernanceService $service)
    {
        $this->checkAccess();
        $categories = $service->getMasterGrid(50);
        return view('gov-classification::governance.index', compact('categories'));
    }

    public function show($id, CategoryGovernanceService $service)
    {
        $this->checkAccess();
        $details = $service->getCategoryDetails($id);
        return view('gov-classification::governance.show', $details);
    }
}