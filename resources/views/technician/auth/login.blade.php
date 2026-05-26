@extends('technician.layout', ['title' => 'Technician Login'])

@section('content')
    <form class="panel" method="POST" action="{{ route('technician.login.store') }}">
        @csrf
        <h1 class="section-title">Technician Login</h1>
        <div class="grid">
            <label>
                Username
                <input name="username" value="{{ old('username') }}" autocomplete="username" required>
            </label>
            <label>
                Password
                <input name="password" type="password" autocomplete="current-password" required>
            </label>
            <label style="display:flex; align-items:center; gap:8px;">
                <input name="remember" type="checkbox" value="1" style="width:auto;">
                Remember this device
            </label>
            <button class="btn primary" type="submit">Login</button>
        </div>
    </form>
@endsection
