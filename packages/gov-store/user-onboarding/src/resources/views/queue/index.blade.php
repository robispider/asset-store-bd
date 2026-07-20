@extends('layouts/default')

@section('title', 'User Onboarding Queue')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-plus text-blue"></i> Mapped Unassigned Employees</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead style="background-color: #f9f9f9;">
                        <tr>
                            <th>Employee Details</th>
                            <th>Created By</th>
                            <th>Territory Scope Tag</th>
                            <th>Creation Date</th>
                            <th class="text-center" style="width: 280px;">Action / Location Assignment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($queue as $item)
                            <tr>
                                <tr>
                                <td>
                                    <strong>{{ $item->user ? ($item->user->first_name . ' ' . $item->user->last_name) : 'Deleted Employee' }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-user"></i> {{ $item->user->username ?? '-' }}</small>
                                </td>
                                <td>{{ $item->creator->first_name ?? 'System' }}</td>
                                <td>
                                    @if($item->geoArea)
                                        <span class="label bg-orange" style="font-size: 11px;">
                                            <i class="fas fa-map-marker-alt"></i> {{ $item->geoArea->en_name }} ({{ ucfirst($item->geoArea->geo_type) }})
                                        </span>
                                    @else
                                        <span class="label bg-purple" style="font-size: 11px;">Company Scoped</span>
                                    @endif
                                </td>
                                <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                <td style="vertical-align: middle;">
                                    {{-- Direct Inline Assignment Form --}}
                                    <form action="{{ route('gov.onboard.assign') }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <input type="hidden" name="onboarding_id" value="{{ $item->id }}">
                                        <div class="input-group input-group-sm">
                                            <select name="location_id" class="form-control select2" required style="width: 180px;">
                                                <option value="">-- Choose Office --</option>
                                                @foreach($locations as $loc)
                                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                                @endforeach
                                            </select>
                                            <span class="input-group-btn">
                                                <button type="submit" class="btn btn-success btn-flat" style="font-weight: bold;">Assign</button>
                                            </span>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 35px;">No unassigned employees in your queue. All accounts are configured and operational.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $queue->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
