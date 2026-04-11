{{-- resources/views/vendor/adminlte/partials/iframe/tab.blade.php --}}
@if (isset($tab['title']))
    {{ is_callable($tab['title']) ? $tab['title']() : __($tab['title']) }}
@endif