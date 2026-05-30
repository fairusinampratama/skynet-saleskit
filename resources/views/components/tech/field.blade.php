@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
])

<label class="grid gap-1.5 text-sm font-semibold text-slate-700">
    <span>{{ $label }}</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-slate-950 focus:border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-600/25']) }}
    >
</label>
