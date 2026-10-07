{{--
    Lab sections 1–10 for one theme × mode. Expects: $theme, $mode, $tokens, $fixtures, $contract,
    $issues, $panel (unique id), $bengali (bool), $compact (matrix panels skip the largest blocks).
--}}
@php
    $contrast = \GovStore\Theming\Build\ThemeValidator::contrastTable($tokens);
    $pairs = [];
    foreach ($contrast as $row) { $pairs[$row['fg']] = $row; }
    $sample = $bengali ? $fixtures->bengaliSample() : $fixtures->englishSample();
    $lines = $fixtures->receiptLines();
    $total = array_sum(array_map(fn ($l) => $l['qty'] * $l['rate'], $lines));
@endphp

{{-- 1 · Colour --}}
<section class="gs-lab-section" data-lab-section="colour" aria-labelledby="{{ $panel }}-colour">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-colour">1 · {{ __('gs-theme::appearance.lab_sections.colour') }}</h3>
    @foreach ($contract as $group => $paths)
        @continue(in_array($group, ['Typography', 'Shape', 'Density', 'Elevation']))
        <h4 class="gs-lab-group">{{ $group }}</h4>
        <div class="gs-lab-swatches">
            @foreach ($paths as $path)
                @php($var = substr(\GovStore\Theming\Build\Contract::cssVar($path), 5))
                <div class="gs-lab-swatch" data-gs-token-name="--gs-{{ $var }}">
                    <span class="gs-lab-swatch__chip" data-gs-swatch-token="{{ $var }}"></span>
                    <span class="gs-lab-swatch__name">{{ $path }}</span>
                    <code class="gs-lab-swatch__value">{{ $tokens[$path] ?? '—' }}</code>
                    @isset($pairs[$path])
                        @php($row = $pairs[$path])
                        <span class="gs-lab-chip gs-lab-chip--{{ $row['pass'] ? 'pass' : 'fail' }}"
                            title="{{ $row['fg'] }} / {{ $row['bg'] }}">
                            {{ $row['ratio'] }}:1 · {{ $row['pass'] ? __('gs-theme::appearance.lab_pass') : __('gs-theme::appearance.lab_fail') }}
                        </span>
                    @endisset
                </div>
            @endforeach
        </div>
    @endforeach
</section>

{{-- 2 · Type --}}
<section class="gs-lab-section" data-lab-section="type" aria-labelledby="{{ $panel }}-type">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-type">2 · {{ __('gs-theme::appearance.lab_sections.type') }}</h3>
    <div class="gs-lab-type">
        <p class="gs-lab-type__display" data-gs-token-name="--gs-font-display">Stock Register 2026</p>
        <h1 class="gs-lab-type__h1">Heading 1 · শিরোনাম</h1>
        <h2 class="gs-lab-type__h2">Heading 2</h2>
        <h3 class="gs-lab-type__h3">Heading 3</h3>
        <h4 class="gs-lab-type__h4">Heading 4</h4>
        <p data-gs-token-name="--gs-font-sans">{{ $fixtures->englishSample() }}</p>
        <p class="gs-bn" lang="bn" data-gs-token-name="--gs-font-bengali">{{ $fixtures->bengaliSample() }}</p>
        <p><small class="gs-muted">Small · {{ __('gs-theme::appearance.mode') }}</small></p>
        <p class="gs-lab-type__mono" data-gs-token-name="--gs-font-mono">GRN-2026-00413 · 0123456789</p>
        <p class="gs-num gs-lab-type__figures">৳ 1,234,567.89 · 98,765.00 · 4,821.50</p>
    </div>
</section>

{{-- 3 · Shape & density --}}
<section class="gs-lab-section" data-lab-section="shape" aria-labelledby="{{ $panel }}-shape">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-shape">3 · {{ __('gs-theme::appearance.lab_sections.shape') }}</h3>
    <div class="gs-lab-shapes">
        @foreach (['sm', 'md', 'lg'] as $r)
            <div class="gs-lab-shape gs-lab-shape--{{ $r }}" data-gs-token-name="--gs-radius-{{ $r }}">radius-{{ $r }}</div>
        @endforeach
        @foreach (['1', '2'] as $s)
            <div class="gs-lab-shape gs-lab-shape--shadow-{{ $s }}" data-gs-token-name="--gs-shadow-{{ $s }}">shadow-{{ $s }}</div>
        @endforeach
    </div>
    <div class="gs-lab-spaces">
        @foreach (range(1, 6) as $s)
            <span class="gs-lab-space gs-lab-space--{{ $s }}" data-gs-token-name="--gs-space-{{ $s }}"></span>
        @endforeach
    </div>
    <div class="gs-lab-heights">
        <div class="gs-lab-height gs-lab-height--row" data-gs-token-name="--gs-size-row-h">row-h</div>
        <div class="gs-lab-height gs-lab-height--control" data-gs-token-name="--gs-size-control-h">control-h</div>
    </div>
</section>

{{-- 4 · Core surfaces (Snipe-IT / AdminLTE markup) --}}
<section class="gs-lab-section" data-lab-section="core" aria-labelledby="{{ $panel }}-core">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-core">4 · {{ __('gs-theme::appearance.lab_sections.core') }}</h3>
    <div class="gs-lab-core">
        <ol class="breadcrumb"><li><a href="#{{ $panel }}">Home</a></li><li><a href="#{{ $panel }}">Assets</a></li><li class="active">List All</li></ol>
        <div class="box box-default">
            <div class="box-header with-border"><h2 class="box-title">Assets</h2></div>
            <div class="box-body">
                <table class="table table-striped table-hover table-bordered">
                    <thead><tr><th>Asset tag</th><th>Name</th><th>Assigned to</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($fixtures->assets() as $i => $asset)
                            <tr @class(['selected' => $i === 1])><td><a href="#{{ $panel }}">{{ $asset['tag'] }}</a></td><td>{{ $asset['name'] }}</td><td>{{ $asset['user'] }}</td><td><x-gs::status-badge :status="$asset['status']" /></td></tr>
                        @endforeach
                    </tbody>
                </table>
                <ul class="pagination pagination-sm"><li><a href="#{{ $panel }}">«</a></li><li class="active"><a href="#{{ $panel }}">1</a></li><li><a href="#{{ $panel }}">2</a></li><li><a href="#{{ $panel }}">»</a></li></ul>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label for="{{ $panel }}-name" class="control-label">Asset name</label>
                    <input id="{{ $panel }}-name" class="form-control" type="text" value="Dell Latitude 5440" required>
                    <p class="help-block">Help text for the field.</p>
                </div>
                <div class="form-group has-error">
                    <label for="{{ $panel }}-serial" class="control-label">Serial</label>
                    <div class="input-group"><span class="input-group-addon">#</span><input id="{{ $panel }}-serial" class="form-control" type="text" value="duplicate"></div>
                    <span class="alert-msg">This serial already exists.</span>
                </div>
                <div class="form-group">
                    <label for="{{ $panel }}-select" class="control-label">Status label (select2)</label>
                    <select id="{{ $panel }}-select" class="form-control js-gs-lab-select2" data-gs-panel="{{ $panel }}">
                        <option>Ready to deploy</option><option>Pending</option><option>Broken</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="{{ $panel }}-date" class="control-label">Purchase date (datepicker)</label>
                    <input id="{{ $panel }}-date" class="form-control js-gs-lab-date" type="text" value="2026-10-07" data-gs-panel="{{ $panel }}">
                </div>
            </div>
            <div class="col-sm-6">
                <p>
                    <button type="button" class="btn btn-primary">Primary</button>
                    <button type="button" class="btn btn-theme">Theme</button>
                    <button type="button" class="btn btn-default">Default</button>
                    <button type="button" class="btn btn-success">Success</button>
                    <button type="button" class="btn btn-warning">Warning</button>
                    <button type="button" class="btn btn-danger">Danger</button>
                </p>
                <p>
                    <span class="label label-default">default</span> <span class="label label-primary">primary</span>
                    <span class="label label-success">success</span> <span class="label label-warning">warning</span>
                    <span class="label label-danger">danger</span> <span class="label label-info">info</span> <span class="badge">12</span>
                </p>
                <div class="alert alert-success">Saved successfully.</div>
                <div class="alert alert-danger">Something went wrong.</div>
                <div class="callout callout-legend"><h4>Legend</h4><p class="callout-subtext">Callout subtext.</p></div>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs"><li class="active"><a href="#{{ $panel }}">Details</a></li><li><a href="#{{ $panel }}">History</a></li></ul>
                    <div class="tab-content"><p>Tab content.</p></div>
                </div>
            </div>
        </div>
        @unless ($compact)
            <div class="row">
                <div class="col-sm-4"><div class="info-box"><span class="info-box-icon bg-green"><i class="fas fa-barcode" aria-hidden="true"></i></span><div class="info-box-content"><span class="info-box-text">Assets</span><span class="info-box-number">12,486</span></div></div></div>
                <div class="col-sm-4"><div class="small-box bg-teal"><div class="inner"><h3>37</h3><p>Pending receipts</p></div></div></div>
                <div class="col-sm-4">
                    <ul class="dropdown-menu gs-lab-static-menu"><li><a href="#{{ $panel }}">Edit profile</a></li><li class="divider"></li><li><a href="#{{ $panel }}">Logout</a></li></ul>
                </div>
            </div>
            <div class="modal-content gs-lab-static-modal">
                <div class="modal-header"><h4 class="modal-title">Confirm checkout</h4></div>
                <div class="modal-body"><p>Check out NAR-000184 to Rahima Akter?</p></div>
                <div class="modal-footer"><button type="button" class="btn btn-default">Cancel</button> <button type="button" class="btn btn-primary">Confirm</button></div>
            </div>
        @endunless
    </div>
</section>

{{-- 5 · UI kit --}}
<section class="gs-lab-section" data-lab-section="kit" aria-labelledby="{{ $panel }}-kit">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-kit">5 · {{ __('gs-theme::appearance.lab_sections.kit') }}</h3>
    <x-gs::page-header title="Goods Receipt GRN-2026-00413" bn="পণ্য প্রাপ্তি" subtitle="Dhaka District Store · received 03 Oct 2026">
        <x-slot:meta><span><i class="fas fa-user" aria-hidden="true"></i> Md. Karim</span><span>3 lines</span></x-slot:meta>
        <x-slot:actions><button type="button" class="gs-btn">Print</button><button type="button" class="gs-btn gs-btn--primary">Post</button></x-slot:actions>
    </x-gs::page-header>
    <div class="gs-lab-row">
        @foreach (['draft', 'ready', 'posted', 'cancelled', 'approved', 'rejected', 'deployed', 'deployable', 'pending', 'undeployable', 'archived'] as $status)
            <x-gs::status-badge :status="$status" />
        @endforeach
        <x-gs::status-badge status="posted" size="lg" />
    </div>
    <x-gs::status-tabs :tabs="$fixtures->statusTabs()" active="draft" />
    <x-gs::bulk-bar for="{{ $panel }}-docs" class="gs-lab-force-visible">
        <button type="button" class="gs-btn">Export</button><button type="button" class="gs-btn gs-btn--danger">Cancel selected</button>
    </x-gs::bulk-bar>
    <x-gs::stepper :steps="$fixtures->steps('ready')" />
    <x-gs::stepper :steps="[['label' => 'Draft', 'state' => 'done'], ['label' => 'Inspection', 'state' => 'blocked'], ['label' => 'Posted', 'state' => 'upcoming']]" />
    <div class="gs-kpis">
        @foreach ($fixtures->kpis() as $kpi)
            <x-gs::kpi :label="$kpi['label']" :value="$kpi['value']" :bn="$bengali ? $kpi['bn'] : null" :delta="$kpi['delta']" :icon="$kpi['icon']" :tone="$kpi['tone']" href="#{{ $panel }}" />
        @endforeach
    </div>
    <x-gs::filter-bar reset-href="#{{ $panel }}" :active="2">
        <div><label class="control-label" for="{{ $panel }}-q">Search</label><input id="{{ $panel }}-q" class="form-control" type="search" value="toner"></div>
        <div><label class="control-label" for="{{ $panel }}-type">Type</label><select id="{{ $panel }}-type" class="form-control"><option>Goods Receipt</option></select></div>
    </x-gs::filter-bar>
    <x-gs::box title="Receipt details" icon="fas fa-file-lines" tone="primary" collapsible>
        <x-slot:tools><button type="button" class="gs-btn gs-btn--ghost">Edit</button></x-slot:tools>
        <x-gs::key-value :items="$fixtures->keyValues()" :columns="$compact ? 1 : 2" />
        <x-slot:footer>Last updated 03 Oct 2026</x-slot:footer>
    </x-gs::box>
    <x-gs::box title="Muted box" tone="muted"><x-gs::empty-state icon="fa-inbox" title="No pending receipts">Everything has been posted.</x-gs::empty-state></x-gs::box>
    <x-gs::table :columns="[['key' => 'item', 'label' => 'Item'], ['key' => 'unit', 'label' => 'Unit'], ['key' => 'qty', 'label' => 'Qty', 'numeric' => true], ['key' => 'rate', 'label' => 'Rate', 'numeric' => true]]"
        :rows="array_map(fn ($l) => $l + ['rate' => number_format($l['rate'], 2)], $lines)" caption="Receipt lines" id="{{ $panel }}-docs" />
    <x-gs::table :columns="[['key' => 'a', 'label' => 'Empty table']]" :rows="[]" />
    <x-gs::form-row label="Supplier" for="{{ $panel }}-supplier" bn="সরবরাহকারী" required help="As printed on the challan.">
        <input id="{{ $panel }}-supplier" class="form-control" type="text" value="Bangladesh Office Supplies Ltd.">
    </x-gs::form-row>
    <x-gs::form-row label="Challan no." for="{{ $panel }}-challan" error="Challan number is required.">
        <input id="{{ $panel }}-challan" class="form-control" type="text">
    </x-gs::form-row>
    @foreach (['info', 'success', 'warning', 'danger'] as $tone)
        <x-gs::alert :tone="$tone" :dismissible="$tone === 'info'">An {{ $tone }} message for this receipt.</x-gs::alert>
    @endforeach
    <x-gs::timeline :items="$fixtures->timeline()" />
</section>

{{-- 6 · General-purpose kit --}}
<section class="gs-lab-section" data-lab-section="general" aria-labelledby="{{ $panel }}-general">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-general">6 · {{ __('gs-theme::appearance.lab_sections.general') }}</h3>
    @php($tones = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'])

    <h4 class="gs-lab-subtitle">Buttons</h4>
    <div class="gs-lab-row">@foreach ($tones as $tone)<x-gs::button :tone="$tone">{{ ucfirst($tone) }}</x-gs::button>@endforeach</div>
    <div class="gs-lab-row">@foreach ($tones as $tone)<x-gs::button :tone="$tone" pill>{{ ucfirst($tone) }}</x-gs::button>@endforeach</div>
    <div class="gs-lab-row">@foreach ($tones as $tone)<x-gs::button :tone="$tone" outline>{{ ucfirst($tone) }}</x-gs::button>@endforeach</div>
    <div class="gs-lab-row">
        <x-gs::button-group label="View">
            <x-gs::button tone="light" icon="fa-table-cells">Grid</x-gs::button>
            <x-gs::button tone="primary" icon="fa-list">List</x-gs::button>
            <x-gs::button tone="light" icon="fa-chart-simple">Chart</x-gs::button>
        </x-gs::button-group>
        <x-gs::button size="sm">Small</x-gs::button>
        <x-gs::button size="lg">Large</x-gs::button>
        <x-gs::button loading>Saving</x-gs::button>
        <x-gs::button tone="secondary" disabled>Disabled</x-gs::button>
        <x-gs::button href="#{{ $panel }}" tone="primary" outline icon="fa-arrow-up-right-from-square">Link</x-gs::button>
    </div>

    <h4 class="gs-lab-subtitle">Badges</h4>
    <div class="gs-lab-row">@foreach ($tones as $tone)<x-gs::badge :tone="$tone">{{ ucfirst($tone) }}</x-gs::badge>@endforeach</div>
    <div class="gs-lab-row">@foreach ($tones as $tone)<x-gs::badge :tone="$tone" outline>{{ ucfirst($tone) }}</x-gs::badge>@endforeach</div>
    <div class="gs-lab-row">@foreach ($tones as $tone)<x-gs::badge :tone="$tone" pill>{{ ucfirst($tone) }}</x-gs::badge>@endforeach</div>
    <div class="gs-lab-row gs-lab-row--loose">
        <x-gs::button>Notifications <x-gs::badge tone="light" pill>4</x-gs::badge></x-gs::button>
        <x-gs::button>Inbox <x-gs::badge tone="danger" floating label="unread">99+</x-gs::badge></x-gs::button>
        <x-gs::button>Profile <x-gs::badge tone="danger" floating label="new activity"></x-gs::badge></x-gs::button>
    </div>

    <h4 class="gs-lab-subtitle">Loaders &amp; progress</h4>
    <div class="gs-lab-row">
        @foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info'] as $tone)<x-gs::spinner :tone="$tone" />@endforeach
        @foreach (['primary', 'success', 'info'] as $tone)<x-gs::spinner type="dots" :tone="$tone" />@endforeach
        @foreach (['primary', 'danger'] as $tone)<x-gs::spinner type="pulse" :tone="$tone" />@endforeach
    </div>
    <div class="gs-lab-grid">
        <x-gs::progress label="Storage usage" :value="50" tone="success" caption="Most data used in the last 3 days" />
        <x-gs::progress label="Bandwidth usage" :value="90" tone="danger" />
        <x-gs::progress label="Audit completion" :value="62" tone="warning" size="lg" />
        <x-gs::progress label="Stock counted" :value="7" :max="12" tone="info" size="sm" />
    </div>

    <h4 class="gs-lab-subtitle">Metric cards</h4>
    <div class="gs-kpis">
        <x-gs::kpi layout="icon-left" icon="fa-user-plus" label="New users" value="205" />
        <x-gs::kpi layout="icon-left" icon="fa-hand-holding-dollar" label="Issued" value="4,021" />
        <x-gs::kpi layout="centered" icon="fa-cloud-arrow-up" label="Uploads today" value="21" />
        <x-gs::kpi layout="centered" icon="fa-bell" label="Alerts" value="8" tone="danger" />
    </div>
    <div class="gs-kpis">
        <x-gs::kpi label="Requests in last 24h" value="1,300" tone="success" :spark="[3, 4, 2, 6, 5, 8, 9]" />
        <x-gs::kpi label="Requests last week" value="6,505" tone="danger" :spark="[8, 5, 9, 5, 7, 4, 2]" />
        <x-gs::kpi label="Storage usage" value="50%" tone="success" :progress="50" caption="Most data used in last 3 days" />
        <x-gs::kpi label="Server status" value="Up" tone="success" icon="fa-circle-check" caption="Last down 4 days ago" />
    </div>

    <h4 class="gs-lab-subtitle">Tiles, cards &amp; lists</h4>
    <div class="gs-lab-grid">
        <x-gs::tile filled icon="fa-database" title="Backups" meta="Total: 32" href="#{{ $panel }}" />
        <x-gs::tile filled icon="fa-server" title="Databases" meta="Total: 302" />
        <x-gs::tile icon="fa-hard-drive" title="Space used" meta="Total: 160 GB" />
        <x-gs::tile icon="fa-download" title="Downloaded" meta="Total: 30 GB" />
    </div>
    <div class="gs-lab-grid">
        <x-gs::card title="Card title" subtitle="Plain content card">
            Some quick example text to build on the card title and make up the bulk of the content.
            <x-slot:actions><a href="#{{ $panel }}">Card link</a><a href="#{{ $panel }}">Another link</a></x-slot:actions>
        </x-gs::card>
        <x-gs::card header="Featured" title="Card title text">
            With supporting text below as a natural lead-in to additional content.
            <x-slot:actions><x-gs::button pill>Go somewhere</x-gs::button></x-slot:actions>
        </x-gs::card>
        <x-gs::card align="center" title="Md. Karim" subtitle="Storekeeper · Dhaka District Store">
            <x-slot:media><div class="gs-lab-card-avatar"><x-gs::avatar name="Md Karim" size="lg" /></div></x-slot:media>
            Responsible for receipts and issues at the district store.
            <x-slot:actions><x-gs::button pill>Contact</x-gs::button></x-slot:actions>
        </x-gs::card>
    </div>
    <div class="gs-lab-grid gs-lab-grid--2">
        <x-gs::box title="Top items issued">
            <x-gs::list>
                <x-gs::list-item icon="fa-print" title="Toner cartridge 85A" subtitle="Consumable · Printing">
                    <x-slot:meta>412</x-slot:meta>
                </x-gs::list-item>
                <x-gs::list-item icon="fa-laptop" title="Laptop, 14-inch" subtitle="Asset · Computing">
                    <x-slot:meta>38</x-slot:meta>
                </x-gs::list-item>
                <x-gs::list-item avatar="Rahima Akter" title="Rahima Akter" subtitle="Office admin" href="#{{ $panel }}">
                    <x-slot:actions><x-gs::button size="sm" pill outline>Follow</x-gs::button></x-slot:actions>
                </x-gs::list-item>
            </x-gs::list>
        </x-gs::box>
        <div>
            <x-gs::list cards>
                <x-gs::list-item icon="fa-keyboard" title="Wireless keyboard and mouse set" subtitle="Accessory">
                    <x-slot:meta>৳ 2,450</x-slot:meta>
                    <x-slot:actions><x-gs::badge tone="success" pill>In stock</x-gs::badge></x-slot:actions>
                </x-gs::list-item>
                <x-gs::list-item icon="fa-chair" title="Office chair, ergonomic" subtitle="Furniture">
                    <x-slot:meta>৳ 9,800</x-slot:meta>
                    <x-slot:actions><x-gs::badge tone="warning" pill>Low</x-gs::badge></x-slot:actions>
                </x-gs::list-item>
            </x-gs::list>
        </div>
    </div>

    <h4 class="gs-lab-subtitle">Tabs &amp; accordion</h4>
    <x-gs::tabs id="{{ $panel }}-wizard" justified label="Registration steps"
        :tabs="[['key' => 'basic', 'label' => 'Basic info'], ['key' => 'office', 'label' => 'Office info'], ['key' => 'docs', 'label' => 'Documents'], ['key' => 'final', 'label' => 'Final']]">
        <x-gs::tab-panel for="basic">
            <div class="gs-lab-grid gs-lab-grid--2">
                <div><label class="control-label" for="{{ $panel }}-first">First name</label><input id="{{ $panel }}-first" class="form-control" type="text" placeholder="First name"></div>
                <div><label class="control-label" for="{{ $panel }}-last">Last name</label><input id="{{ $panel }}-last" class="form-control" type="text" placeholder="Last name"></div>
            </div>
            <x-gs::button tone="light" pill>Next</x-gs::button>
        </x-gs::tab-panel>
        <x-gs::tab-panel for="office">Office details go here.</x-gs::tab-panel>
        <x-gs::tab-panel for="docs">Attach supporting documents.</x-gs::tab-panel>
        <x-gs::tab-panel for="final">Review and submit.</x-gs::tab-panel>
    </x-gs::tabs>
    <x-gs::accordion name="{{ $panel }}-faq">
        <x-gs::accordion-item title="How are receipts posted?" icon="fa-circle-question" open>Receipts move from Draft to Ready after inspection, then to Posted.</x-gs::accordion-item>
        <x-gs::accordion-item title="Who can cancel a document?" icon="fa-circle-question">The office admin, before the document is posted.</x-gs::accordion-item>
        <x-gs::accordion-item title="Can I print a register?" icon="fa-circle-question">Yes. Every register has a print view in the ledger style.</x-gs::accordion-item>
    </x-gs::accordion>

    <h4 class="gs-lab-subtitle">Alert appearances</h4>
    @foreach (['success', 'info', 'warning', 'danger'] as $tone)
        <x-gs::alert :tone="$tone" appearance="outline" :title="ucfirst($tone).'!'" dismissible>This is an outline {{ $tone }} alert.</x-gs::alert>
    @endforeach
    @foreach (['success', 'danger'] as $tone)
        <x-gs::alert :tone="$tone" appearance="card" :title="ucfirst($tone).'!'">This is a card {{ $tone }} alert.</x-gs::alert>
    @endforeach
</section>

{{-- 7 · Patterns --}}
<section class="gs-lab-section" data-lab-section="patterns" aria-labelledby="{{ $panel }}-patterns">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-patterns">7 · {{ __('gs-theme::appearance.lab_sections.patterns') }}</h3>
    <h4 class="gs-lab-group">List page</h4>
    <x-gs::page-header title="Store Documents Hub" subtitle="{{ $sample }}" :level="2">
        <x-slot:actions><button type="button" class="gs-btn gs-btn--primary">+ New receipt</button></x-slot:actions>
    </x-gs::page-header>
    <x-gs::status-tabs :tabs="$fixtures->statusTabs()" active="all" />
    <x-gs::table>
        <thead><tr><th scope="col"><input type="checkbox" aria-label="Select all"></th><th scope="col">Document</th><th scope="col">Type</th><th scope="col">Office</th><th scope="col">Date</th><th scope="col" class="gs-num">Value (BDT)</th><th scope="col">Status</th></tr></thead>
        <tbody>
            @foreach ($fixtures->documents() as $i => $doc)
                <tr @if ($i === 1) aria-selected="true" @endif>
                    <td><input type="checkbox" aria-label="Select {{ $doc['no'] }}" @checked($i === 1)></td>
                    <td><a href="#{{ $panel }}">{{ $doc['no'] }}</a></td><td>{{ $doc['type'] }}</td><td>{{ $doc['office'] }}</td><td>{{ $doc['date'] }}</td>
                    <td class="gs-num">{{ number_format($doc['value'], 2) }}</td><td><x-gs::status-badge :status="$doc['status']" /></td>
                </tr>
            @endforeach
        </tbody>
    </x-gs::table>
    @unless ($compact)
        <h4 class="gs-lab-group">Goods Receipt workspace</h4>
        <x-gs::stepper :steps="$fixtures->steps('ready')" />
        <div class="row">
            <div class="col-md-8">
                <x-gs::key-value :items="$fixtures->keyValues()" />
                <x-gs::table table="ledger">
                    <thead><tr><th scope="col">Item</th><th scope="col">Unit</th><th scope="col" class="gs-num">Qty</th><th scope="col" class="gs-num">Rate</th><th scope="col" class="gs-num">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr><td>{{ $line['item'] }}</td><td>{{ $line['unit'] }}</td><td class="gs-num">{{ $line['qty'] }}</td><td class="gs-num">{{ number_format($line['rate'], 2) }}</td><td class="gs-num">{{ number_format($line['qty'] * $line['rate'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <x-slot:totals><tr><th scope="row" colspan="4">Total</th><td class="gs-num">{{ number_format($total, 2) }}</td></tr></x-slot:totals>
                </x-gs::table>
            </div>
            <div class="col-md-4"><x-gs::box title="Activity"><x-gs::timeline :items="$fixtures->timeline()" /></x-gs::box></div>
        </div>
        <h4 class="gs-lab-group">Dashboard</h4>
        <div class="gs-kpis">
            @foreach ($fixtures->kpis() as $kpi)
                <x-gs::kpi :label="$kpi['label']" :value="$kpi['value']" :delta="$kpi['delta']" :icon="$kpi['icon']" :tone="$kpi['tone']" />
            @endforeach
        </div>
    @endunless
</section>

{{-- 8 · Charts --}}
<section class="gs-lab-section" data-lab-section="charts" aria-labelledby="{{ $panel }}-charts">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-charts">8 · {{ __('gs-theme::appearance.lab_sections.charts') }}</h3>
    <div class="gs-lab-charts">
        @foreach (($compact ? ['horizontalBar', 'pie'] : ['horizontalBar', 'pie', 'line']) as $type)
            <div class="gs-lab-chart"><canvas class="js-gs-lab-chart" data-type="{{ $type }}" data-chart='@json($fixtures->chart())' height="200" aria-label="{{ $type }} chart" role="img"></canvas></div>
        @endforeach
    </div>
</section>

{{-- 9 · Print --}}
<section class="gs-lab-section" data-lab-section="print" aria-labelledby="{{ $panel }}-print">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-print">9 · {{ __('gs-theme::appearance.lab_sections.print') }}</h3>
    {{-- Print preview: same theme, always light. --}}
    <div class="gs-lab-print" data-skin="{{ $theme->key }}" data-theme="light" data-gs-table="ledger" data-gs-status-style="{{ $theme->variants['status_style'] ?? 'tinted' }}">
        <x-gs::document doc-no="GRN-2026-00413" date="03-10-2026" title="Goods Receipt Note · পণ্য প্রাপ্তি নোট">
            <x-slot:emblem><i class="fas fa-landmark fa-2x" aria-hidden="true"></i></x-slot:emblem>
            <x-slot:office><h2>Government of the People's Republic of Bangladesh</h2><div>Dhaka District Store</div></x-slot:office>
            <x-gs::table>
                <thead><tr><th scope="col">Item</th><th scope="col" class="gs-num">Qty</th><th scope="col" class="gs-num">Amount</th></tr></thead>
                <tbody>@foreach ($lines as $line)<tr><td>{{ $line['item'] }}</td><td class="gs-num">{{ $line['qty'] }}</td><td class="gs-num">{{ number_format($line['qty'] * $line['rate'], 2) }}</td></tr>@endforeach</tbody>
                <x-slot:totals><tr><th scope="row" colspan="2">Total</th><td class="gs-num">{{ number_format($total, 2) }}</td></tr></x-slot:totals>
            </x-gs::table>
            <x-slot:signatures><div class="gs-signature">Storekeeper</div><div class="gs-signature">Inspection Committee</div><div class="gs-signature">Approving Officer</div></x-slot:signatures>
            <x-slot:footer>Generated by the National Asset Register</x-slot:footer>
        </x-gs::document>
    </div>
</section>

{{-- 10 · Validation --}}
<section class="gs-lab-section" data-lab-section="validation" aria-labelledby="{{ $panel }}-validation">
    <h3 class="gs-lab-section__title" id="{{ $panel }}-validation">10 · {{ __('gs-theme::appearance.lab_sections.validation') }}</h3>
    @php($modeIssues = array_values(array_filter($issues, fn ($i) => $i['mode'] === null || $i['mode'] === $mode)))
    @if ($modeIssues === [])
        <x-gs::alert tone="success">{{ __('gs-theme::appearance.lab_no_issues') }}</x-gs::alert>
    @else
        <x-gs::table :columns="[['key' => 'level', 'label' => 'Level'], ['key' => 'check', 'label' => 'Check'], ['key' => 'message', 'label' => 'Message']]" :rows="$modeIssues" density="compact" />
    @endif
</section>
