{{-- Scaled approximation of the AdminLTE shell (the real header/sidebar are position:fixed and page-global). --}}
<div class="gs-lab-shell" aria-hidden="true">
    <div class="gs-lab-shell__header">
        <span class="gs-lab-shell__brand">National Asset Register</span>
        <span class="gs-lab-shell__search">Search…</span>
        <span class="gs-lab-shell__user"><i class="fas fa-user-circle"></i> Admin</span>
    </div>
    <div class="gs-lab-shell__body">
        <ul class="gs-lab-shell__nav">
            @foreach (['fa-gauge' => 'Dashboard', 'fa-barcode' => 'Assets', 'fa-file-lines' => 'Store Documents', 'fa-truck-ramp-box' => 'Goods Receipt', 'fa-chart-column' => 'Reports'] as $icon => $label)
                <li @class(['is-active' => $loop->index === 2])><i class="fas {{ $icon }} gs-lab-shell__icon"></i><span>{{ $label }}</span></li>
            @endforeach
            <li class="gs-lab-shell__section">Administration</li>
            <li><i class="fas fa-gear gs-lab-shell__icon"></i><span>Settings</span></li>
        </ul>
        <div class="gs-lab-shell__content">
            <div class="gs-lab-shell__crumbs">Home › Store Documents</div>
            <div class="gs-lab-shell__card"><strong>Store Documents Hub</strong><span class="gs-muted"> · 128 documents</span></div>
            <div class="gs-lab-shell__footer">National Asset Register · v8</div>
        </div>
    </div>
</div>
