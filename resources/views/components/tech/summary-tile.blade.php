@props(['label', 'value', 'valueId' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-slate-50 p-3']) }}>
    <strong @if ($valueId) id="{{ $valueId }}" @endif class="block text-base font-extrabold text-slate-950">{{ $value }}</strong>
    <span class="text-sm text-slate-500">{{ $label }}</span>
</div>
