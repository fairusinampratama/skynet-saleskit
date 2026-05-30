@extends('technician.layout', ['title' => __('registration.title.detail')])

@section('content')
    @php
        $readiness = $registration->technicianReadiness();
        $statusVariant = $registration->status === 'needs_revision' ? 'danger' : ($registration->status === 'draft' ? 'warn' : 'ok');
    @endphp

    <div class="grid gap-4">
        <x-tech.panel>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold leading-tight">{{ $registration->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $registration->phone }} · {{ $registration->area?->name ?? 'Belum pilih area' }}</p>
                </div>
                <x-tech.status-badge :variant="$statusVariant">{{ __('registration.status.'.$registration->status) }}</x-tech.status-badge>
            </div>
            @if (in_array($registration->status, ['draft', 'needs_revision'], true))
                <div class="mt-3">
                    <x-tech.button :href="route('technician.registrations.edit', $registration)" icon="pencil-square">{{ __('ui.actions.continue_editing') }}</x-tech.button>
                </div>
            @endif
        </x-tech.panel>

        <x-tech.panel>
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Kelengkapan</div>
                    <h2 class="text-base font-extrabold">Data Teknisi</h2>
                </div>
                @if ($readiness['complete'])
                    <x-tech.status-badge variant="ok">{{ __('ui.common.complete') }}</x-tech.status-badge>
                @else
                    <x-tech.status-badge variant="warn">{{ count($readiness['missing']) }} kurang</x-tech.status-badge>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <x-tech.summary-tile label="KTP" :value="$readiness['has_ktp'] ? 'Siap' : 'Diperlukan'" />
                <x-tech.summary-tile label="GPS" :value="$readiness['has_gps'] ? 'Siap' : 'Diperlukan'" />
                <x-tech.summary-tile label="Bukti" :value="$readiness['has_evidence'] ? 'Siap' : 'Diperlukan'" />
                <x-tech.summary-tile label="Diperbarui" :value="$registration->updated_at->format('d M')" />
            </div>
            @if (! $readiness['complete'])
                <p class="mt-3 text-sm text-slate-500">Kurang: {{ collect($readiness['missing'])->join(', ') }}.</p>
            @endif
        </x-tech.panel>

        <x-tech.panel>
            <h2 class="text-base font-extrabold">Pelanggan</h2>
            <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <p><strong>NIK:</strong> {{ $registration->nik ?: '-' }}</p>
                <p><strong>Email:</strong> {{ $registration->email ?: '-' }}</p>
                <p><strong>Paket:</strong> {{ $registration->package ?: '-' }}</p>
            </div>
        </x-tech.panel>

        <x-tech.panel>
            <h2 class="text-base font-extrabold">Alamat</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="font-bold">Alamat KTP</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $registration->ktp_full_address ?: '-' }}</p>
                </div>
                <div>
                    <p class="font-bold">Alamat Instalasi</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $registration->installation_full_address ?: '-' }}</p>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                <span>{{ collect([$registration->village, $registration->district, $registration->city, $registration->province])->filter()->join(', ') ?: 'Detail wilayah belum ada' }}</span>
                <span>{{ $registration->latitude && $registration->longitude ? $registration->latitude.', '.$registration->longitude : 'GPS belum ada' }}</span>
            </div>
        </x-tech.panel>

        @if ($registration->admin_notes)
            <x-tech.panel>
                <h2 class="text-base font-extrabold">Catatan Admin</h2>
                <p class="mt-2 text-sm text-slate-700">{{ $registration->admin_notes }}</p>
            </x-tech.panel>
        @endif
    </div>
@endsection
