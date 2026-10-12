@extends('layouts/default')

@section('title', 'Location Unassigned')

@section('content')
<div class="govorg-theme">
<div class="text-center org-inline-24bf522c">
    <div class="box box-danger org-inline-6e38c678">
        <div class="box-body">
            <span class="org-inline-aa2830a2"><i class="fas fa-map-marker-alt"></i></span>
            <h2 class="org-inline-71bce23b">Office Location Missing</h2>
            <p class="text-muted org-inline-c019fc47">
                Your user account is not currently mapped to an active office location in the database. You must be assigned to an office location before you can view the catalog and submit requests.
            </p>
            <p class="help-block org-inline-d75cbef7">Please contact your local Office Administrator or ICT Officer to update your profile location inside Snipe-IT.</p>
            <div class="org-inline-e1a4a08e">
                <a href="{{ url('/') }}" class="btn btn-default"><i class="fas fa-home"></i> Return Home</a>
            </div>
        </div>
    </div>
</div>
</div>
@endsection