{{-- Media list (people, items, files). `divided` adds rules between rows; `cards` floats each row as its own card. --}}
@props(['divided' => true, 'cards' => false])
<ul {{ $attributes->class(['gs-list', 'gs-list--divided' => $divided && ! $cards, 'gs-list--cards' => $cards]) }} role="list">
    {{ $slot }}
</ul>
