<!-- TAB C: LOCAL STAFF DIRECTORY -->
                <div class="tab-pane" id="tab_employees">
                    <div class="row" style="padding: 15px 0;">
                        <div class="col-md-8">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr><th>{{ __('office_membership::member.hub_table_employee_name') }}</th><th>{{ __('office_membership::member.hub_table_username') }}</th><th>{{ __('office_membership::member.hub_table_email') }}</th><th>{{ __('office_membership::member.hub_table_job_title') }}</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($localStaff as $user)
                                        <tr>
                                            <td><strong>{{ $user->present()->fullName }}</strong></td>
                                            <td>{{ $user->username }}</td>
                                            <td>{{ $user->email ?: '-' }}</td>
                                            <td>{{ $user->jobtitle ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted" style="padding: 30px;">{{ __('office_membership::member.hub_no_employees') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- CLAIM EMPLOYEE WIDGET -->
                        <div class="col-md-4">
                            <div class="box box-success">
                                <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-user-plus"></i> {{ __('office_membership::member.hub_claim_label') }}</h3></div>
                                <form action="{{ route('gov.membership.claim', $location->id) }}" method="POST">
                                    @csrf
                                    <div class="box-body">
                                        <p class="text-muted" style="font-size:12px;">{{ __('office_membership::member.hub_claim_hint') }}</p>
                                        <div class="form-group">
                                            <select name="user_id" class="form-control select2" required style="width: 100%;">
                                                <option value="">{{ __('office_membership::member.hub_claim_select_placeholder') }}</option>
                                                @foreach(\App\Models\User::whereHas('memberships', function($q) { $q->whereIn('status', ['release_requested', 'released']); })->get() as $u)
                                                    <option value="{{ $u->id }}">{{ $u->present()->fullName }} ({{ $u->location->name ?? 'Floating' }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-success btn-block"><i class="fas fa-check"></i> {{ __('office_membership::member.hub_claim_button') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>