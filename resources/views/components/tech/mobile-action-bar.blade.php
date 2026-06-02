@props(['columns' => 'two'])

<div {{ $attributes->merge(['class' => 'fixed inset-x-0 bottom-0 z-30 grid '.($columns === 'one' ? 'grid-cols-1' : 'grid-cols-2').' gap-2 border-t border-slate-200 bg-white/95 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-8px_22px_rgb(15_23_42_/_0.10)] md:hidden']) }}>
    {{ $slot }}
</div>
