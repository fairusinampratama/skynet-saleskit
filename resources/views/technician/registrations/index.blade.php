@extends('technician.layout', ['title' => __('registration.title.index')])

@section('content')
    <section class="relative mb-4 overflow-hidden rounded-3xl border border-white/10 bg-slate-950 px-4 py-5 text-white shadow-[0_24px_70px_rgb(15_23_42_/_0.22)] tech-dark-grid md:px-6">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgb(14_165_233_/_0.34),transparent_34rem),radial-gradient(circle_at_bottom_right,rgb(245_158_11_/_0.24),transparent_30rem)]"></div>
        <div class="relative grid gap-5 lg:grid-cols-[1fr_320px] lg:items-end">
            <div>
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 font-mono text-[10px] font-black uppercase tracking-widest text-amber-200">
                    <x-heroicon-o-map-pin class="h-4 w-4" />
                    Portal Penugasan & Work Order
                </div>
                <h1 class="font-display text-3xl font-black leading-tight md:text-4xl">{{ __('registration.title.index') }}</h1>
                <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-300">Pantau draf, antrean onboarding pelanggan, dan kesiapan data teknisi sebelum dispatch review admin.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Daftar Work Order Tersedia</div>
                <div class="mt-2 flex items-end justify-between gap-3">
                    <strong class="font-mono text-3xl font-black text-white">{{ $registrations->total() }}</strong>
                    <x-tech.status-badge style="portal" variant="dark">Sinkron Lokal</x-tech.status-badge>
                </div>
                <p class="mt-2 text-xs font-semibold leading-5 text-slate-300">Filter status untuk memilih antrean registrasi yang perlu dilanjutkan.</p>
            </div>
        </div>
    </section>

    <form class="mb-4 grid gap-3 rounded-2xl border border-white/70 bg-white/85 p-3 shadow-sm backdrop-blur md:grid-cols-[minmax(220px,1fr)_auto]" method="GET" action="{{ route('technician.registrations.index') }}">
        <x-tech.field style="portal" label="Cari work order" name="q" type="search" :value="$search" placeholder="Pelanggan, telepon, NIK, atau area" />
        @if ($activeStatus)
            <input type="hidden" name="status" value="{{ $activeStatus }}">
        @endif
        <div class="flex items-end">
            <x-tech.button style="portal" type="submit" icon="magnifying-glass" full>{{ __('ui.actions.search') }}</x-tech.button>
        </div>
    </form>

    <div class="sticky top-[65px] z-10 -mx-3 mb-4 flex gap-2 overflow-x-auto border-y border-slate-200/70 bg-white/90 px-3 py-2 shadow-sm backdrop-blur md:top-[69px] md:mx-0 md:rounded-2xl md:border md:px-2" aria-label="Filter status registrasi">
        <a class="shrink-0 rounded-xl border px-3 py-2 text-xs font-black {{ $activeStatus ? 'border-slate-200 bg-white text-slate-500' : 'border-slate-950 bg-slate-950 text-white' }}" href="{{ route('technician.registrations.index', $search !== '' ? ['q' => $search] : []) }}">{{ __('ui.common.all') }}</a>
        @foreach ($statuses as $filterStatus)
            <a class="shrink-0 rounded-xl border px-3 py-2 text-xs font-black {{ $activeStatus === $filterStatus ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50' }}" href="{{ route('technician.registrations.index', array_filter(['status' => $filterStatus, 'q' => $search ?: null])) }}">
                {{ __('registration.status.'.$filterStatus) }}
            </a>
        @endforeach
    </div>

    <div class="grid gap-3">
        @forelse ($registrations as $registration)
            @php
                $readiness = $registration->technicianReadiness();
                $nextAction = match ($registration->status) {
                    'draft' => 'Lanjutkan draf',
                    'submitted' => 'Menunggu review',
                    'approved' => 'Disetujui',
                    default => 'Lihat detail',
                };
                $statusVariant = $registration->status === 'draft' ? 'warn' : 'ok';
            @endphp
            <a class="tech-glow-card grid gap-3 rounded-2xl border border-white/70 bg-white/90 p-4 shadow-sm backdrop-blur transition active:scale-[0.995]" href="{{ route('technician.registrations.show', $registration) }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">WO {{ str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT) }}</div>
                        <strong class="mt-1 block truncate font-display text-lg font-black text-slate-950">{{ $registration->name }}</strong>
                    </div>
                    <x-tech.status-badge style="portal" :variant="$statusVariant">{{ __('registration.status.'.$registration->status) }}</x-tech.status-badge>
                </div>
                <div class="grid gap-2 text-sm font-semibold text-slate-500 md:grid-cols-4">
                    <span class="truncate">WhatsApp: {{ $registration->phone }}</span>
                    <span class="truncate">Paket: {{ $registration->package ?: 'Belum pilih paket' }}</span>
                    <span class="truncate">Area: {{ $registration->area?->name ?? 'Belum pilih area' }}</span>
                    <span class="truncate">Update: {{ $registration->updated_at->format('d M Y H:i') }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                    <span class="text-sm font-bold text-slate-600">{{ $nextAction }}</span>
                    @if ($readiness['complete'])
                        <x-tech.status-badge style="portal" variant="ok">{{ __('ui.common.complete') }}</x-tech.status-badge>
                    @else
                        <x-tech.status-badge style="portal" variant="warn">{{ count($readiness['missing']) }} kurang</x-tech.status-badge>
                    @endif
                </div>
            </a>
        @empty
            <x-tech.panel style="portal">
                <h2 class="text-base font-extrabold">Registrasi tidak ditemukan</h2>
                <p class="mt-1 text-sm text-slate-500">Buat registrasi baru atau ubah pencarian dan filter status.</p>
            </x-tech.panel>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $registrations->links() }}
    </div>

    <x-tech.mobile-action-bar style="portal" columns="one">
        <x-tech.button style="portal" :href="route('technician.registrations.create')" icon="plus" full>{{ __('ui.actions.new_registration') }}</x-tech.button>
    </x-tech.mobile-action-bar>

    <div class="hidden md:fixed md:bottom-5 md:right-5 md:block">
        <x-tech.button style="portal" :href="route('technician.registrations.create')" icon="plus">{{ __('ui.actions.new_registration') }}</x-tech.button>
    </div>
@endsection
