{{--
    Native <details> accordion: works without JavaScript. Give it a `name` to make it exclusive —
    opening one item closes the others (<details name>; older browsers simply allow several open).
--}}
@props(['name' => null])
<div {{ $attributes->class('gs-accordion') }}>
    {{ $slot }}
</div>
