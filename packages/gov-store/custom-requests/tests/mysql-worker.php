<?php

use App\Models\User;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\RequestNumberService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__.'/mysql-bootstrap.php';
customRequestsMysqlApplication();

if (($argv[1] ?? '') === 'numbers') {
    if (isset($argv[2])) {
        Carbon::setTestNow(Carbon::create((int) $argv[2], 1, 1));
    }
    $numbers = [];
    for ($i = 0; $i < 25; $i++) {
        $numbers[] = app(RequestNumberService::class)->generate();
    }
    echo json_encode($numbers);
    exit;
}

$request = Request::findOrFail((int) ($argv[1] ?? 0));
$actor = User::findOrFail((int) ($argv[2] ?? 0));
$context = app(TenantContext::class);
$context->locationId = (int) $request->office_id;
$context->companyId = (int) DB::table('locations')->where('id', $context->locationId)->value('company_id');
$decisions = $request->items->mapWithKeys(fn ($line) => [$line->id => ['status' => 'approved', 'qty' => $line->requested_qty]])->all();
app(ApprovalService::class)->processDecision($request, $actor, $decisions);
echo json_encode(['approval_status' => $request->fresh()->approval_status]);
