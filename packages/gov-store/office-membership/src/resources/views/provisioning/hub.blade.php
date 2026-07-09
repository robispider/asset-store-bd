<!-- TAB C: LOCAL STAFF DIRECTORY -->
                <div class="tab-pane" id="tab_employees">
                    <div class="row" style="padding: 15px 0;">
                        <div class="col-md-8">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr><th>Employee Name</th><th>Username</th><th>Email Address</th><th>Job Title</th></tr>
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
                                        <tr><td colspan="4" class="text-center text-muted" style="padding: 30px;">No employees are mapped here.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- CLAIM EMPLOYEE WIDGET -->
                        <div class="col-md-4">
                            <div class="box box-success">
                                <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-user-plus"></i> Claim Incoming Employee</h3></div>
                                <form action="{{ route('gov.membership.claim', $location->id) }}" method="POST">
                                    @csrf
                                    <div class="box-body">
                                        <p class="text-muted" style="font-size:12px;">Search for employees who have requested release from their previous office to add them to this location.</p>
                                        <div class="form-group">
                                            <select name="user_id" class="form-control select2" required style="width: 100%;">
                                                <option value="">-- Select Released Employee --</option>
                                                @foreach(\App\Models\User::whereHas('memberships', function($q) { $q->whereIn('status', ['release_requested', 'released']); })->get() as $u)
                                                    <option value="{{ $u->id }}">{{ $u->present()->fullName }} ({{ $u->location->name ?? 'Floating' }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-success btn-block"><i class="fas fa-check"></i> Approve Transfer & Claim</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>