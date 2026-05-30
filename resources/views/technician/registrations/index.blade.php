@extends('technician.layout', ['title' => __('registration.title.index')])

@section('content')
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold leading-tight">{{ __('registration.title.index') }}</h1>
            <p class="mt-1 text-sm text-slate-500">Pantau draf, revisi, dan registrasi pelanggan yang sudah dikirim.</p>
        </div>
    </div>

    <form class="mb-4 grid gap-3 md:grid-cols-[minmax(220px,1fr)_auto]" method="GET" action="{{ route('technician.registrations.index') }}">
        <x-tech.field label="Cari registrasi" name="q" type="search" :value="$search" placeholder="Pelanggan, telepon, NIK, atau area" />
        @if ($activeStatus)
            <input type="hidden" name="status" value="{{ $activeStatus }}">
        @endif
        <div class="flex items-end">
            <x-tech.button type="submit" icon="magnifying-glass" full>{{ __('ui.actions.search') }}</x-tech.button>
        </div>
    </form>

    <div class="sticky top-[57px] z-10 -mx-3 mb-4 flex gap-2 overflow-x-auto border-b border-slate-200 bg-slate-100/95 px-3 py-2 backdrop-blur md:top-[61px] md:mx-0 md:border-b-0 md:px-0" aria-label="Filter status registrasi">
        <a class="shrink-0 rounded-full border px-3 py-2 text-xs font-extrabold {{ $activeStatus ? 'border-slate-200 bg-white text-slate-500' : 'border-amber-700 bg-amber-50 text-amber-900' }}" href="{{ route('technician.registrations.index', $search !== '' ? ['q' => $search] : []) }}">{{ __('ui.common.all') }}</a>
        @foreach ($statuses as $filterStatus)
            <a class="shrink-0 rounded-full border px-3 py-2 text-xs font-extrabold {{ $activeStatus === $filterStatus ? 'border-amber-700 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-500' }}" href="{{ route('technician.registrations.index', array_filter(['status' => $filterStatus, 'q' => $search ?: null])) }}">
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
                    'needs_revision' => 'Perbaiki revisi',
                    'submitted' => 'Menunggu review',
                    'approved' => 'Disetujui',
                    'cancelled' => 'Dibatalkan',
                    default => 'Lihat detail',
                };
                $statusVariant = $registration->status === 'needs_revision' ? 'danger' : ($registration->status === 'draft' ? 'warn' : 'ok');
            @endphp
            <a class="grid gap-2 rounded-lg border border-slate-200 bg-white p-4 active:scale-[0.995]" href="{{ route('technician.registrations.show', $registration) }}">
                <div class="flex items-start justify-between gap-3">
                    <strong class="text-slate-950">{{ $registration->name }}</strong>
                    <x-tech.status-badge :variant="$statusVariant">{{ __('registration.status.'.$registration->status) }}</x-tech.status-badge>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                    <span>{{ $registration->phone }}</span>
                    <span>{{ $registration->package ?: 'Belum pilih paket' }}</span>
                    <span>{{ $registration->area?->name ?? 'Belum pilih area' }}</span>
                    <span>Diperbarui {{ $registration->updated_at->format('d M Y H:i') }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-slate-500">{{ $nextAction }}</span>
                    @if ($readiness['complete'])
                        <x-tech.status-badge variant="ok">{{ __('ui.common.complete') }}</x-tech.status-badge>
                    @else
                        <x-tech.status-badge variant="warn">{{ count($readiness['missing']) }} kurang</x-tech.status-badge>
                    @endif
                </div>
            </a>
        @empty
            <x-tech.panel>
                <h2 class="text-base font-extrabold">Registrasi tidak ditemukan</h2>
                <p class="mt-1 text-sm text-slate-500">Buat registrasi baru atau ubah pencarian dan filter status.</p>
            </x-tech.panel>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $registrations->links() }}
    </div>

    <x-tech.mobile-action-bar columns="one">
        <x-tech.button :href="route('technician.registrations.create')" icon="plus" full>{{ __('ui.actions.new_registration') }}</x-tech.button>
    </x-tech.mobile-action-bar>

    <div class="hidden md:fixed md:bottom-5 md:right-5 md:block">
        <x-tech.button :href="route('technician.registrations.create')" icon="plus">{{ __('ui.actions.new_registration') }}</x-tech.button>
    </div>
@endsection
