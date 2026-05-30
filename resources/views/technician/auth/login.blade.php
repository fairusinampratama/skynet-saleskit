@extends('technician.layout', ['title' => 'Masuk Teknisi'])

@section('content')
    <form class="mx-auto mt-[8vh] max-w-md rounded-lg border border-slate-200 bg-white p-5" method="POST" action="{{ route('technician.login.store') }}">
        @csrf
        <h1 class="text-lg font-extrabold">Masuk Teknisi</h1>
        <div class="mt-4 grid gap-3">
            <x-tech.field label="Nama Pengguna" name="username" :value="old('username')" autocomplete="username" required />
            <x-tech.field label="Kata Sandi" name="password" type="password" autocomplete="current-password" required />
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input class="h-4 w-4 accent-amber-700" name="remember" type="checkbox" value="1">
                Ingat perangkat ini
            </label>
            <x-tech.button type="submit" icon="arrow-right-on-rectangle" full>Masuk</x-tech.button>
        </div>
    </form>
@endsection
