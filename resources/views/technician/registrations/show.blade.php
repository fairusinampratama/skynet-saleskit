@extends('technician.layout', ['title' => 'Registration Detail'])

@section('content')
    <div class="panel">
        <div class="button-row" style="justify-content: space-between;">
            <h1 class="section-title" style="margin:0;">{{ $registration->customer->name }}</h1>
            <span class="status">{{ str_replace('_', ' ', $registration->status) }}</span>
        </div>
        <p class="muted">{{ $registration->customer->phone }} · {{ $registration->area?->name ?? 'No area selected' }}</p>
        @if (in_array($registration->status, ['draft', 'needs_revision'], true))
            <a class="btn primary" href="{{ route('technician.registrations.edit', $registration) }}">Continue Editing</a>
        @endif
    </div>

    <div class="panel">
        <h2 class="section-title">Customer</h2>
        <p><strong>NIK:</strong> {{ $registration->customer->nik ?: '-' }}</p>
        <p><strong>Email:</strong> {{ $registration->customer->email ?: '-' }}</p>
    </div>

    <div class="panel">
        <h2 class="section-title">Installation Address</h2>
        @php($address = $registration->customer->addresses->firstWhere('address_type', 'installation'))
        <p>{{ $address?->full_address ?: '-' }}</p>
        <p class="muted">{{ collect([$address?->village, $address?->district, $address?->city, $address?->province])->filter()->join(', ') }}</p>
        <p class="muted">{{ $address?->latitude }}, {{ $address?->longitude }}</p>
    </div>

    @if ($registration->admin_notes)
        <div class="panel">
            <h2 class="section-title">Admin Notes</h2>
            <p>{{ $registration->admin_notes }}</p>
        </div>
    @endif
@endsection
