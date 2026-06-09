@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'icon' => null,
    'full' => false,
    'style' => 'default',
])

@php
    $isPortal = $style === 'portal';
    $base = $isPortal
        ? 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-extrabold shadow-sm transition duration-150 focus:outline-none focus:ring-2 focus:ring-amber-500/30 active:scale-[0.985] disabled:cursor-not-allowed disabled:opacity-60'
        : 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-amber-600/25 disabled:cursor-not-allowed disabled:opacity-60';
    $variants = $isPortal
        ? [
            'primary' => 'border border-amber-400 bg-amber-500 text-white shadow-amber-500/20 hover:bg-amber-600',
            'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700',
            'ghost' => 'border border-slate-200 bg-white/70 text-slate-600 hover:bg-slate-50 hover:text-slate-950',
            'dark' => 'border border-white/10 bg-slate-950 text-white hover:border-amber-300/60 hover:text-amber-100',
            'danger' => 'border border-rose-500 bg-rose-600 text-white hover:bg-rose-700',
        ]
        : [
            'primary' => 'bg-amber-700 text-white hover:bg-amber-800',
            'secondary' => 'bg-slate-200 text-slate-950 hover:bg-slate-300',
            'ghost' => 'border border-slate-200 bg-white text-slate-900 hover:bg-slate-50',
            'danger' => 'bg-rose-700 text-white hover:bg-rose-800',
        ];
    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($full ? 'w-full' : ''));
@endphp

@if ($href)
    <a {{ $attributes->merge(['href' => $href, 'class' => $classes]) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" />
        @endif
        <span data-button-label>{{ $slot }}</span>
    </a>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" />
        @endif
        <span data-button-label>{{ $slot }}</span>
    </button>
@endif
