@extends('technician.layout', ['title' => 'Masuk Teknisi'])

@section('content')
    <form class="mx-auto mt-[7vh] max-w-lg overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-[0_24px_70px_rgb(15_23_42_/_0.18)] backdrop-blur" method="POST" action="{{ route('technician.login.store') }}">
        @csrf
        <div class="relative overflow-hidden bg-slate-950 px-5 py-6 text-white tech-dark-grid md:px-6">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgb(14_165_233_/_0.35),transparent_26rem),radial-gradient(circle_at_bottom_right,rgb(245_158_11_/_0.28),transparent_24rem)]"></div>
            <div class="relative">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 font-mono text-[10px] font-black uppercase tracking-widest text-amber-200">
                    <x-heroicon-o-wrench-screwdriver class="h-4 w-4" />
                    Portal Teknisi Lapangan
                </div>
                <h1 class="font-display text-3xl font-black leading-tight">Masuk Work Order</h1>
                <p class="mt-2 text-sm font-semibold leading-6 text-slate-300">Akses pendaftaran pelanggan, OCR KTP, GPS lokasi, dan dispatch registrasi fiber.</p>
            </div>
        </div>
        <div class="grid gap-3 p-5 md:p-6">
            <x-tech.field style="portal" label="Nama Pengguna" name="username" :value="old('username')" autocomplete="username" required />
            <x-tech.field style="portal" label="Kata Sandi" name="password" type="password" autocomplete="current-password" required />
            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700">
                <input class="h-4 w-4 accent-amber-500" name="remember" type="checkbox" value="1">
                Ingat perangkat ini
            </label>
            <x-tech.button style="portal" type="submit" icon="arrow-right-on-rectangle" full>Masuk</x-tech.button>
        </div>
    </form>
@endsection
