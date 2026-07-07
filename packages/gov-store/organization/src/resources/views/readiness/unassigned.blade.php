@extends('layouts/default')

@section('title', 'Location Unassigned')

@section('content')
<div class="text-center" style="max-width: 550px; margin: 60px auto;">
    <div class="box box-danger" style="border-top: 3px solid #dd4b39; border-radius: 8px; padding: 30px;">
        <div class="box-body">
            <span style="font-size: 50px; color: #dd4b39;"><i class="fas fa-map-marker-alt"></i></span>
            <h2 style="font-weight: bold; margin-top: 15px;">Office Location Missing</h2>
            <p class="text-muted" style="font-size: 15px; margin-bottom: 25px;">
                Your user account is not currently mapped to an active office location in the database. You must be assigned to an office location before you can view the catalog and submit requests.
            </p>
            <p class="help-block" style="font-size: 13px;">Please contact your local Office Administrator or ICT Officer to update your profile location inside Snipe-IT.</p>
            <div style="margin-top: 30px;">
                <a href="{{ url('/') }}" class="btn btn-default"><i class="fas fa-home"></i> Return Home</a>
            </div>
        </div>
    </div>
</div>
@endsection