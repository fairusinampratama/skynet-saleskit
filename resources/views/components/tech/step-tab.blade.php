@props(['active' => false])

<button
    type="button"
    {{ $attributes->merge(['class' => 'shrink-0 rounded-md px-2.5 py-1.5 text-xs font-extrabold leading-5 transition '.($active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900')]) }}
>
    {{ $slot }}
</button>
