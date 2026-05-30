<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SalesKit Teknisi' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <div class="min-h-screen">
        <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-3.5 py-3 backdrop-blur">
            <a class="text-base font-extrabold tracking-normal" href="{{ route('technician.registrations.index') }}">SalesKit</a>
            @auth
                <form method="POST" action="{{ route('technician.logout') }}">
                    @csrf
                    <x-tech.button variant="secondary" type="submit" icon="arrow-right-on-rectangle">Keluar</x-tech.button>
                </form>
            @endauth
        </header>
        <main class="mx-auto w-full max-w-5xl px-3 py-4 pb-24 md:px-5 md:py-6 md:pb-12">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <strong>Periksa data registrasi.</strong>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
