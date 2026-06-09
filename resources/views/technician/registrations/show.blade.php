@extends('technician.layout', ['title' => __('registration.title.detail')])

@section('content')
    @php
        $readiness = $registration->technicianReadiness();
        $statusVariant = $registration->status === 'draft' ? 'warn' : 'ok';
    @endphp

    <div class="grid gap-4">
        <section class="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-950 px-4 py-5 text-white shadow-[0_24px_70px_rgb(15_23_42_/_0.22)] tech-dark-grid md:px-6">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgb(14_165_233_/_0.34),transparent_34rem),radial-gradient(circle_at_bottom_right,rgb(245_158_11_/_0.24),transparent_30rem)]"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 font-mono text-[10px] font-black uppercase tracking-widest text-amber-200">
                        <x-heroicon-o-clipboard-document-check class="h-4 w-4" />
                        Detail Work Order
                    </div>
                    <h1 class="font-display text-3xl font-black leading-tight md:text-4xl">{{ $registration->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-300">{{ $registration->phone }} · {{ $registration->area?->name ?? 'Belum pilih area' }}</p>
                    <p class="mt-1 font-mono text-[10px] font-bold uppercase tracking-widest text-slate-500">WO {{ str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT) }} · Updated {{ $registration->updated_at->format('d M Y H:i') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-tech.status-badge style="portal" variant="dark">{{ __('registration.status.'.$registration->status) }}</x-tech.status-badge>
                    @if ($registration->status === 'draft')
                        <x-tech.button style="portal" :href="route('technician.registrations.edit', $registration)" icon="pencil-square">{{ __('ui.actions.continue_editing') }}</x-tech.button>
                    @endif
                </div>
            </div>
        </section>

        <x-tech.panel style="portal">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Kelengkapan BAST awal</div>
                    <h2 class="font-display text-lg font-black text-slate-950">Data Teknisi</h2>
                </div>
                @if ($readiness['complete'])
                    <x-tech.status-badge style="portal" variant="ok">{{ __('ui.common.complete') }}</x-tech.status-badge>
                @else
                    <x-tech.status-badge style="portal" variant="warn">{{ count($readiness['missing']) }} kurang</x-tech.status-badge>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <x-tech.summary-tile style="portal" label="KTP" :value="$readiness['has_ktp'] ? 'Siap' : 'Diperlukan'" />
                <x-tech.summary-tile style="portal" label="GPS" :value="$readiness['has_gps'] ? 'Siap' : 'Diperlukan'" />
                <x-tech.summary-tile style="portal" label="Foto" :value="$readiness['has_evidence'] ? 'Ada Foto' : 'Opsional'" />
                <x-tech.summary-tile style="portal" label="Diperbarui" :value="$registration->updated_at->format('d M')" />
            </div>
            @if (! $readiness['complete'])
                <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-800">Kurang: {{ collect($readiness['missing'])->join(', ') }}.</p>
            @endif
        </x-tech.panel>

        <x-tech.panel style="portal">
            <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Profil Pelanggan Terkait</div>
            <h2 class="font-display text-lg font-black text-slate-950">Pelanggan</h2>
            <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <p class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2"><strong>NIK:</strong> {{ $registration->nik ?: '-' }}</p>
                <p class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2"><strong>Paket:</strong> {{ $registration->package ?: '-' }}</p>
            </div>
        </x-tech.panel>

        <x-tech.panel style="portal">
            <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Koordinat & alamat pemasangan</div>
            <h2 class="font-display text-lg font-black text-slate-950">Alamat</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3">
                    <p class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Alamat KTP</p>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $registration->ktp_full_address ?: '-' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3">
                    <p class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Alamat Instalasi</p>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $registration->installation_full_address ?: '-' }}</p>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 rounded-xl border border-slate-200 bg-white/80 px-3 py-2 text-sm font-bold text-slate-600">
                <span>GPS: {{ $registration->latitude && $registration->longitude ? $registration->latitude.', '.$registration->longitude : 'GPS belum ada' }}</span>
            </div>
        </x-tech.panel>
    </div>
@endsection
