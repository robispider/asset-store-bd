<?php

namespace GovStore\Experimentation\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Experimentation\Services\ExperimentManager;
use GovStore\Experimentation\Services\ExperimentWiper;
use GovStore\Experimentation\Services\RecordRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExperimentController extends Controller
{
    public function index()
    {
        return view('experiments::index', ['runs' => ExperimentRun::latest()->paginate(20), 'profiles' => config('govstore-experiments.profiles')]);
    }

    public function populate(Request $request, ExperimentManager $manager)
    {
        $data = $request->validate(['profile' => ['required', Rule::in(array_keys(config('govstore-experiments.profiles')))], 'seed' => 'required|integer|min:0|max:2147483647', 'label' => 'nullable|string|max:150']);
        try {
            $run = $manager->populate($request->user(), $data['profile'], (int) $data['seed'], $data['label'] ?? null);
            $manager->dispatch($run, $request->user());

            return redirect()->route('gov.experiments.show', $run);
        } catch (\Throwable $e) {
            if (isset($run)) {
                return redirect()->route('gov.experiments.show', $run);
            }
            if (! $e instanceof \RuntimeException) {
                throw $e;
            }

            return back()->withErrors(['experiment' => $e->getMessage()]);
        }
    }

    public function show(ExperimentRun $run, RecordRegistry $records)
    {
        return view('experiments::show', ['run' => $run, 'counts' => $records->counts($run)]);
    }

    public function status(ExperimentRun $run)
    {
        return response()->json(['id' => $run->id, 'status' => $run->status, 'phase' => $run->phase,
            'completed_units' => count($run->report['units'] ?? []), 'error' => $run->error])->header('Cache-Control', 'no-store');
    }

    public function resume(Request $request, ExperimentRun $run, ExperimentManager $manager)
    {
        try {
            $manager->resume($run, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['experiment' => $e->getMessage()]);
        }

        return redirect()->route('gov.experiments.show', $run);
    }

    public function preview(Request $request, ExperimentRun $run, ExperimentWiper $wiper)
    {
        $data = $request->validate(['scope' => ['nullable', Rule::in(['dataset', 'database'])]]);
        try {
            $preview = $wiper->preview($run, $data['scope'] ?? 'dataset', $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['experiment' => $e->getMessage()]);
        }

        return view('experiments::wipe', ['run' => $run, 'preview' => $preview]);
    }

    public function recover(Request $request, ExperimentRun $run, ExperimentManager $manager)
    {
        try {
            $manager->recover($run, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['experiment' => $e->getMessage()]);
        }

        return redirect()->route('gov.experiments.show', $run);
    }

    public function cleanup(Request $request, ExperimentRun $run, ExperimentWiper $wiper)
    {
        $wiper->cleanupFiles($run, $request->user());

        return redirect()->route('gov.experiments.show', $run);
    }

    public function wipe(Request $request, ExperimentRun $run, ExperimentManager $manager)
    {
        $data = $request->validate(['scope' => ['required', Rule::in(['dataset', 'database'])], 'confirmation' => 'required|string|max:255', 'fingerprint' => 'required|string|size:64']);
        try {
            $manager->requestWipe($run, $request->user(), $data['scope'], $data['confirmation'], $data['fingerprint']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['experiment' => $e->getMessage()]);
        }

        return redirect()->route('gov.experiments.show', $run);
    }

    public function accounts(ExperimentRun $run, RecordRegistry $records)
    {
        abort_if($run->wiped_at, 404);
        $users = DB::table('users')->whereIn('id', $records->ids($run, 'users'))->orderBy('id')->get();

        return response()->streamDownload(function () use ($run, $users) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['username', 'password', 'name', 'company_id', 'home_office_id', 'office_roles', 'oversight_role'], ',', '"', '');
            foreach ($users as $user) {
                $roles = DB::table('gov_office_responsibilities')->where('user_id', $user->id)->pluck('role_slug')->implode(', ');
                $oversight = DB::table('gov_company_admins')->where('user_id', $user->id)->exists() ? 'company_admin' : (DB::table('gov_ict_jurisdictions')->where('user_id', $user->id)->exists() ? 'ict_officer' : '');
                fputcsv($stream, [$user->username, $run->password, $user->display_name ?: $user->first_name.' '.$user->last_name, $user->company_id, $user->location_id, $roles, $oversight], ',', '"', '');
            }
            fclose($stream);
        }, 'bangladesh-experiment-'.$run->id.'-accounts.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
