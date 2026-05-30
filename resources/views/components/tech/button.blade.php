@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'icon' => null,
    'full' => false,
])

@php
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-amber-600/25 disabled:cursor-not-allowed disabled:opacity-60';
    $variants = [
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
