<?php

namespace GovStore\Experimentation\Services;

use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use GovStore\AssetTagging\Http\Controllers\EnablementController;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Classification\Models\FinanceItem;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\Committee\Database\seeders\CommitteeSeeder;
use GovStore\Committee\Models\Committee;
use GovStore\Committee\Services\CatalogService;
use GovStore\Committee\Services\CommitteeAssignmentService;
use GovStore\Committee\Services\CommitteeLedger;
use GovStore\Committee\Services\CommitteeMembershipService;
use GovStore\Committee\Services\CommitteeService;
use GovStore\Committee\Services\ExternalMemberService;
use GovStore\Committee\Services\OrderIntake;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\OfficeMembership\Services\RoleAssignmentService;
use GovStore\OfficeMembership\Services\RoleHandshakeService;
use GovStore\StoreOperations\Http\Controllers\DocumentWorkspaceController;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\AccessController;
use GovStore\Theming\Http\Controllers\AssignmentController;
use GovStore\Theming\Models\ThemePreference;
use GovStore\Theming\ThemeManager;
use GovStore\UserOnboarding\Services\UserOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExperimentPackageScenarios
{
    public function __construct(private ActorContext $actors, private RecordRegistry $records) {}

    public function populate(ExperimentRun $run, User $root, array $offices, array $people, callable $unit, callable $post): void
    {
        $unit('typed-metadata', fn () => $this->metadata($run, $root, $offices));
        foreach (['draft', 'active', 'suspended', 'dissolved'] as $state) {
            $unit('committee-'.$state, fn () => $this->committee($run, $root, $offices[0], $state));
        }
        $unit('committee-order-intake', fn () => $this->orderIntake($run, $offices[0]));
        $unit('appearance', fn () => $this->appearance($run, $root, $offices, $people));
        $unit('handshake-decisions', fn () => $this->handshakes($offices[0]));
        $unit('access-decisions', fn () => $this->access($offices[0]));
        $unit('onboarding-decisions', function () use ($run) {
            $ids = DB::table('gov_user_onboardings')->whereIn('user_id', $this->records->ids($run, 'users'))
                ->where('status', 'WAITING')->orderBy('id')->limit(2)->pluck('id');
            foreach ($ids as $id) {
                app(UserOnboardingService::class)->decide($id, 'reject', 'Fictional duplicate registration');
            }

            return $ids->all();
        });
        $unit('transfer', fn () => $this->transfer($run, $root, $offices, $post));
        $unit('takeover', fn () => $this->takeover($run, $root, $offices[0], $post));
    }

    private function metadata(ExperimentRun $run, User $root, array $offices): array
    {
        $store = app(MetadataValueStore::class);
        $ids = [];
        foreach (collect($offices)->groupBy('company_id') as $companyId => $companyOffices) {
            $locations = [];
            foreach ($companyOffices->values() as $index => $office) {
                $locations[] = ['code' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'name' => mb_substr($office->name, 0, 120)];
            }
            $ids[] = $store->put('company', $companyId, OfficeTagFacts::CODELISTS,
                ['locations' => $locations, 'divisions' => [['code' => '01', 'name' => 'Fictional stores'], ['code' => '02', 'name' => 'Fictional administration']]],
                null, $root->id, 'Fictional company code lists')->id;
            foreach ($companyOffices->values() as $index => $office) {
                $ids[] = $store->put('office', $office->id, OfficeTagFacts::OFFICE,
                    ['location_code' => $locations[$index]['code'], 'division_codes' => ['01', '02'], 'default_division_code' => '01'],
                    null, $root->id, 'Fictional office tagging facts')->id;
            }
        }
        // Never enable issuance by writing metadata around the package's installation gates.
        $defaults = app(ProductTypeFinanceDefault::class);
        $financeItems = FinanceItem::where('status', 'active')->orderBy('gov_code')->get();
        foreach ($this->records->ids($run, 'categories') as $categoryId) {
            $finance = $financeItems->first(fn ($item) => $defaults->rejection($item->gov_code, (int) $categoryId) === null);
            abort_unless($defaults->canEdit($root, (int) $categoryId), 403);
            $ids[] = $store->put('category', (int) $categoryId, $defaults::NAMESPACE, ['gov_code' => $finance?->gov_code],
                null, $root->id, 'Fictional product default; reuse reviewed Finance masters only')->id;
        }
        foreach ($offices as $office) {
            $this->actors->run($root, $office, function () use ($office) {
                app(EnablementController::class)
                    ->disable($this->request(['reason' => 'Fictional setup; issuance requires an explicit gated rehearsal']), $office->id);
            });
        }

        return ['metadata_ids' => $ids, 'issuance' => 'disabled', 'reason' => 'Managed tags require explicit rehearsal; issued tags are permanent.'];
    }

    private function request(array $data): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => auth()->user());
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    private function appearance(ExperimentRun $run, User $root, array $offices, array $people): array
    {
        $manager = app(ThemeManager::class);
        $theme = array_key_first($manager->repository()->published());
        if (! $theme) {
            throw new RuntimeException('Publish a bundled theme before generating appearance scenarios.');
        }
        foreach ($people as $index => $person) {
            ThemePreference::create(['user_id' => $person->id, 'theme' => null, 'mode' => ['light', 'dark', 'system'][$index % 3]]);
        }
        $this->actors->run($root, $offices[0], function () use ($offices, $theme) {
            $controller = app(AssignmentController::class);
            $controller->update($this->request(['theme' => $theme, 'enforced' => false]), 'company', $offices[0]->company_id);
            $controller->update($this->request(['theme' => $theme, 'enforced' => true]), 'office', $offices[0]->id);
        });

        return ['theme' => $theme];
    }

    private function handshakes(Location $office): array
    {
        $service = app(RoleHandshakeService::class);
        $keeper = User::withoutGlobalScopes()->findOrFail(DB::table('gov_office_responsibilities')->where('location_id', $office->id)->where('role_slug', 'storekeeper')->value('user_id'));
        $recipient = User::withoutGlobalScopes()->where('location_id', $office->id)->where('id', '!=', $keeper->id)->orderByDesc('id')->firstOrFail();
        $ids = [];
        foreach (['reject', 'cancel', 'accept'] as $action) {
            $proposal = $this->actors->run($keeper, $office, fn () => $service->proposeHandshake($office->id, 'storekeeper', $keeper->id, $recipient->id, 'Fictional role handover'), 'storekeeper');
            $actor = $action === 'cancel' ? $keeper : $recipient;
            $this->actors->run($actor, $office, function () use ($service, $proposal, $actor, $action) {
                match ($action) {
                    'reject' => $service->rejectHandshake($proposal->id, $actor->id, 'Fictional decline'),
                    'cancel' => $service->cancelHandshake($proposal->id, $actor->id),
                    default => $service->acceptHandshake($proposal->id, $actor->id),
                };
            });
            $ids[] = $proposal->id;
        }
        // Restore the original keeper through another real accepted handover.
        $back = $this->actors->run($recipient, $office, fn () => $service->proposeHandshake($office->id, 'storekeeper', $recipient->id, $keeper->id), 'storekeeper');
        $this->actors->run($keeper, $office, fn () => $service->acceptHandshake($back->id, $keeper->id));
        $ids[] = $back->id;
        $assignments = app(RoleAssignmentService::class);
        $proposal = $this->actors->run($keeper, $office, fn () => $assignments->proposeTransfer($office->id, 'storekeeper', $keeper->id, $recipient->id), 'storekeeper');
        $this->actors->run($recipient, $office, fn () => $assignments->acceptTransfer($proposal->id, $recipient->id));
        $restore = $this->actors->run($recipient, $office, fn () => $assignments->proposeTransfer($office->id, 'storekeeper', $recipient->id, $keeper->id), 'storekeeper');
        $this->actors->run($keeper, $office, fn () => $assignments->acceptTransfer($restore->id, $keeper->id));

        return $ids;
    }

    private function access(Location $office): array
    {
        $admin = User::withoutGlobalScopes()->findOrFail(DB::table('gov_location_profiles')->where('location_id', $office->id)->value('office_admin_id'));
        $employee = User::withoutGlobalScopes()->where('location_id', $office->id)->where('id', '!=', $admin->id)->orderByDesc('id')->firstOrFail();
        $ids = [];
        foreach (['declined', 'approved'] as $decision) {
            $this->actors->run($employee, $office, function () {
                app(AccessController::class)->store(
                    $this->request(['ability' => 'committee.manage', 'reason' => 'Fictional short-term registrar cover', 'expires_at' => now()->addDays(2)->toDateTimeString()]),
                    app(TenantContext::class));
            });
            $id = DB::table('gov_access_requests')->where('user_id', $employee->id)->where('location_id', $office->id)->where('status', 'pending')->value('id');
            $this->actors->run($admin, $office, fn () => app(AccessController::class)->review(
                $this->request(['decision' => $decision, 'review_note' => 'Fictional access review']), $id,
                app(TenantContext::class), app(RoleAssignmentService::class)), 'office_admin');
            $ids[] = $id;
        }

        return ['request_ids' => $ids];
    }

    private function committee(ExperimentRun $run, User $root, Location $office, string $state): array
    {
        return $this->actors->run($root, $office, function () use ($run, $office, $state) {
            (new CommitteeSeeder)->run();
            $type = DB::table('gov_committee_types')->where('owner_company_id', $office->company_id)->where('code', 'DEMO')->first();
            if (! $type) {
                $result = app(CatalogService::class)->save('types', null, ['scope' => 'ministry', 'code' => 'DEMO',
                    'name_en' => 'Fictional stock committee', 'name_bn' => 'পরীক্ষামূলক মজুদ কমিটি', 'category' => 'inventory',
                    'default_term_basis' => 'FISCAL_YEAR', 'allowed_scope_types' => ['office'], 'allow_concurrent' => true,
                    'is_active' => true, 'change_reason' => 'Fictional ministry sample only; no legal composition claimed',
                    'composition_policy' => ['strength' => ['min' => 3, 'max' => 5, 'odd_only' => true],
                        'presiding' => ['exactly' => 1, 'roles' => ['chairperson']], 'secretary' => ['min' => 0, 'max' => 1],
                        'external' => ['min' => 0, 'outside' => 'office'], 'technical_expert' => ['min' => 0],
                        'declaration_required' => false, 'quorum' => ['min_present' => 2], 'nomination' => 'BY_NAME', 'incompatible_duties' => []]]);
                $type = (object) ['id' => $result['id']];
                app(CatalogService::class)->save('purposes', null, ['scope' => 'ministry', 'purpose_code' => 'storeops.receipt.inspection',
                    'committee_type_id' => $type->id, 'priority' => 50, 'allow_ancestor_fallback' => false, 'is_active' => true,
                    'change_reason' => 'Fictional registry binding only; receipt inspection consumer is not implemented']);
            }
            $service = app(CommitteeService::class);
            $date = $run->anchor_date->copy()->timezone('Asia/Dhaka')->toDateString();
            $committee = $service->createDraft(['committee_type_id' => $type->id,
                'name_en' => ucfirst($state).' sample committee · '.$run->report['display_id'],
                'name_bn' => 'পরীক্ষামূলক কমিটি '.$state.' · '.$run->report['display_id'], 'term_basis' => 'FISCAL_YEAR', 'effective_from' => $date]);
            if ($state === 'draft') {
                return ['committee_id' => $committee->id, 'state' => 'DRAFT'];
            }
            $paths = [];
            try {
                $order = $this->order($run, $committee, 'CONSTITUTION', $date);
                $paths[] = $order->attachment_path;
                app(CommitteeAssignmentService::class)->assignScope($committee->id, ['scope_type' => 'office', 'scope_id' => (string) $office->id,
                    'effective_from' => $date, 'order_id' => $order->id]);
                $members = User::withoutGlobalScopes()->where('location_id', $office->id)->orderBy('id')->limit(3)->get();
                foreach ($members as $index => $person) {
                    $membership = app(CommitteeMembershipService::class);
                    $seat = $membership->addSeat($committee->id, ['seat_role_code' => $index === 0 ? 'chairperson' : 'member',
                        'seat_no' => $index + 1, 'holder_kind' => $index === 2 ? 'EXTERNAL' : 'PERSON']);
                    $identity = ['user_id' => $person->id];
                    if ($index === 2) {
                        $external = app(ExternalMemberService::class)->create([
                            'full_name_en' => 'Fictional technical adviser', 'full_name_bn' => 'পরীক্ষামূলক কারিগরি উপদেষ্টা',
                            'designation_en' => 'Sample expert', 'designation_bn' => 'পরীক্ষামূলক বিশেষজ্ঞ',
                            'organization_name_en' => 'Fictional research organization', 'organization_name_bn' => 'পরীক্ষামূলক গবেষণা প্রতিষ্ঠান',
                            'organization_kind' => 'PRIVATE_EXPERT']);
                        $identity = ['external_member_id' => $external->id];
                    }
                    $tenure = $membership->appoint($committee->id, $seat->id, $identity + ['from_date' => $date, 'order_id' => $order->id]);
                    if ($index === 0) {
                        $this->actors->run($person, $office, function () use ($run, $membership, $committee, $tenure, $date) {
                            app(ExperimentEvidence::class)->withPdf('Fictional personal declaration', fn ($file) => $membership->recordDeclaration(
                                $committee->id, $tenure->id, ['filed_on' => $date], $file));
                            app(ExperimentFiles::class)->track($run, 'committee_private', $tenure->fresh()->declaration_attachment_path);
                        }, 'office_admin');
                    }
                }
                $service->activate($committee->id, ['order_id' => $order->id]);
                if (in_array($state, ['suspended', 'dissolved'], true)) {
                    $kind = $state === 'suspended' ? 'SUSPENSION' : 'DISSOLUTION';
                    $change = $this->order($run, $committee, $kind, $date);
                    $paths[] = $change->attachment_path;
                    $service->changeState($committee->id, $state === 'suspended' ? 'suspend' : 'dissolve',
                        ['date' => $date, 'order_id' => $change->id, 'reason' => 'Fictional lifecycle example', 'confirmation' => $committee->committee_number]);
                }
                if (app(CommitteeLedger::class)->verifyChain($committee->lineage_id)) {
                    throw new RuntimeException('Fictional committee ledger failed integrity verification.');
                }

                return ['committee_id' => $committee->id, 'state' => strtoupper($state)];
            } catch (\Throwable $error) {
                foreach ($paths as $path) {
                    Storage::disk('committee_private')->delete($path);
                }
                throw $error;
            }
        });
    }

    private function order(ExperimentRun $run, Committee $committee, string $kind, string $date)
    {
        return app(ExperimentEvidence::class)->withPdf($run->report['display_id'].' '.$kind, function ($file) use ($run, $committee, $kind, $date) {
            $order = app(CommitteeService::class)->addOrder($committee->id, ['kind' => $kind,
                'memo_no' => 'SAMPLE-'.$committee->committee_number.'-'.$kind, 'issued_on' => $date,
                'issuing_authority_name' => 'Fictional office administrator', 'issuing_authority_designation_en' => 'Sample only',
                'remarks' => 'Generated fictional demonstration; no legal authority.'], $file);
            app(ExperimentFiles::class)->track($run, 'committee_private', $order->attachment_path);

            return $order;
        });
    }

    private function transfer(ExperimentRun $run, User $root, array $offices, callable $post): array
    {
        $sourceOffice = $offices[0];
        $destination = collect($offices)->first(fn ($o) => $o->id !== $sourceOffice->id && (int) $o->company_id === (int) $sourceOffice->company_id);
        if (! $destination) {
            throw new RuntimeException('Transfer recipe needs two fictional offices in the same company.');
        }

        return $this->actors->run($root, $sourceOffice, function () use ($run, $root, $sourceOffice, $destination, $post) {
            $source = Consumable::whereIn('id', $this->records->ids($run, 'consumables'))->where('location_id', $sourceOffice->id)->orderBy('id')->firstOrFail();
            $target = Consumable::withoutGlobalScopes()->whereIn('id', $this->records->ids($run, 'consumables'))
                ->where('location_id', $destination->id)->where('category_id', $source->category_id)->orderBy('id')->first();
            $target = $this->actors->run($root, $destination, function () use ($target, $source, $destination, $root) {
                if (! $target) {
                    $target = $source->replicate();
                    $target->forceFill(['location_id' => $destination->id, 'qty' => 0, 'created_by' => $root->id]);
                }
                $target->forceFill(['name' => $source->name, 'manufacturer_id' => $source->manufacturer_id, 'model_number' => $source->model_number]);
                if (! $target->save()) {
                    throw new RuntimeException('Could not prepare fictional transfer destination stock.');
                }

                return $target;
            });
            $document = app(GoodsReceiptService::class)->saveDraft([
                'destination_location_id' => $destination->id, 'transfer_reason' => 'Fictional inter-office supply'],
                [['type' => 'consumable', 'id' => $source->id, 'qty' => 1, 'unit_cost' => 250]], $root->id, null, 'transfer');
            $document->items()->firstOrFail()->metadata()->create(['field_key' => 'destination_stockable_id', 'row_index' => 0, 'value' => $target->id]);
            $post($document, $root);

            return ['document_id' => $document->id];
        });
    }

    private function takeover(ExperimentRun $run, User $root, Location $office, callable $post): array
    {
        $document = $this->actors->run($root, $office, function () use ($run, $root, $office) {
            $item = Consumable::whereIn('id', $this->records->ids($run, 'consumables'))->where('location_id', $office->id)->firstOrFail();

            return app(GoodsReceiptService::class)->saveDraft([], [
                ['type' => 'consumable', 'id' => $item->id, 'qty' => 1, 'unit_cost' => 250]], $root->id);
        });
        $keeper = User::withoutGlobalScopes()->findOrFail(DB::table('gov_office_responsibilities')->where('location_id', $office->id)->where('role_slug', 'storekeeper')->value('user_id'));
        $this->actors->run($keeper, $office, function () use ($document, $keeper, $post) {
            app(DocumentWorkspaceController::class)->takeover(
                $this->request(['reason' => 'Fictional drafter unavailable; keeper completes the document']), 'receipt', $document->id);
            $document->references()->create(['reference_type' => 'Nothi / Approval Letter', 'reference_number' => 'Fictional takeover approval']);
            $post($document, $keeper);
        }, 'storekeeper');

        return ['document_id' => $document->id, 'drafter_id' => $root->id, 'poster_id' => $keeper->id];
    }

    private function orderIntake(ExperimentRun $run, Location $office): array
    {
        $admin = User::withoutGlobalScopes()->findOrFail(DB::table('gov_location_profiles')->where('location_id', $office->id)->value('office_admin_id'));

        return $this->actors->run($admin, $office, function () use ($run, $office, $admin) {
            $result = app(ExperimentEvidence::class)->withPdf('Fictional unfinished committee order', fn ($file) => app(OrderIntake::class)->save([
                'intake_location_id' => $office->id, 'intake_company_id' => $office->company_id, 'intake_revision' => 0,
                'memo_no' => 'Fictional draft · '.$run->report['display_id'], 'job' => 'form', 'name_en' => 'Unfinished sample committee order',
                'name_bn' => 'অসমাপ্ত পরীক্ষামূলক কমিটির আদেশ'], $file));
            $row = DB::table('gov_committee_order_intakes')->where('user_id', $admin->id)->where('location_id', $office->id)->where('company_id', $office->company_id)->first();
            $this->records->record($run, 'gov_committee_order_intakes', $row);
            $attachment = json_decode($row->attachment, true);
            app(ExperimentFiles::class)->track($run, 'committee_private', $attachment['attachment_path']);

            return ['user_id' => $admin->id, 'location_id' => $office->id, 'revision' => $result['revision']];
        }, 'office_admin');
    }
}
