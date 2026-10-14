{{--
    A hierarchy path such as Division › District › Upazila, so similarly named places can be told apart.
    items: ['Dhaka Division', 'Dhaka District'] or [['label' => …, 'bn' => …]]. Not navigation: use a
    breadcrumb for that.
--}}
@props(['items' => [], 'label' => null])
<span {{ $attributes->class('gs-trail') }} @if ($label) aria-label="{{ $label }}" @endif>
    @foreach ($items as $item)
        @php($text = is_array($item) ? $item['label'] : $item)
        @if (! $loop->first)<i class="fas fa-angle-right gs-trail__sep" aria-hidden="true"></i><span class="gs-sr-only">,</span>@endif
        <span class="gs-trail__part">{{ $text }}@if (is_array($item) && ! empty($item['bn'])) <span class="gs-bn gs-trail__bn" lang="bn">{{ $item['bn'] }}</span>@endif</span>
    @endforeach
</span>
