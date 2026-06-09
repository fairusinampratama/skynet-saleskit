@props(['active' => false, 'style' => 'default'])

@php
    $classes = $style === 'portal'
        ? 'shrink-0 rounded-xl px-3 py-2 text-xs font-black leading-5 transition '.($active ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-950')
        : 'shrink-0 rounded-md px-2.5 py-1.5 text-xs font-extrabold leading-5 transition '.($active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900');
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => $classes]) }}
>
    {{ $slot }}
</button>
