@extends('technician.layout', ['title' => 'My Registrations'])

@section('content')
    <div class="button-row" style="justify-content: space-between; margin-bottom: 14px;">
        <h1 class="section-title" style="margin:0;">My Registrations</h1>
        <a class="btn primary" href="{{ route('technician.registrations.create') }}">New Registration</a>
    </div>

    <div class="list">
        @forelse ($registrations as $registration)
            <a class="item" href="{{ route('technician.registrations.show', $registration) }}">
                <div class="button-row" style="justify-content: space-between;">
                    <strong>{{ $registration->customer->name }}</strong>
                    <span class="status">{{ str_replace('_', ' ', $registration->status) }}</span>
                </div>
                <div class="muted">{{ $registration->customer->phone }} · {{ $registration->area?->name ?? 'No area selected' }}</div>
                <div class="muted">{{ $registration->created_at->format('d M Y H:i') }}</div>
            </a>
        @empty
            <div class="panel">
                <p class="muted">No registrations yet.</p>
            </div>
        @endforelse
    </div>

    <div style="margin-top: 14px;">
        {{ $registrations->links() }}
    </div>
@endsection
