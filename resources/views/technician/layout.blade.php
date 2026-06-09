<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SalesKit Teknisi' }}</title>
    <script>
        (() => {
            const storageKey = 'saleskit-tech-theme';
            const savedTheme = localStorage.getItem(storageKey);
            const systemDark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)').matches : false;
            const theme = savedTheme || (systemDark ? 'dark' : 'light');

            document.documentElement.dataset.techTheme = theme;
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tech-shell min-h-screen text-slate-950 antialiased">
    <div class="min-h-screen">
        <header class="sticky top-0 z-20 border-b border-white/10 bg-slate-950/88 px-3.5 py-3 text-white shadow-[0_10px_32px_rgb(2_6_23_/_0.28)] backdrop-blur md:px-5">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3">
                <a class="group flex min-w-0 items-center gap-3" href="{{ route('technician.registrations.index') }}">
                    <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-amber-300/30 bg-amber-400/10 text-amber-300">
                        <span class="absolute inset-1 rounded-xl border border-amber-300/20 tech-orbit-pulse"></span>
                        <x-heroicon-o-bolt class="relative h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-display text-base font-black tracking-normal">SalesKit FieldOps</span>
                        <span class="block truncate font-mono text-[10px] font-bold uppercase tracking-widest text-slate-400">GPON onboarding portal</span>
                    </span>
                </a>
            @auth
                <form method="POST" action="{{ route('technician.logout') }}">
                    @csrf
                    <x-tech.button style="portal" variant="dark" type="submit" icon="arrow-right-on-rectangle">Keluar</x-tech.button>
                </form>
            @endauth
                <button
                    type="button"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-slate-200 shadow-sm transition hover:border-amber-300/60 hover:text-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500/30"
                    data-tech-theme-toggle
                    aria-label="Ganti mode tampilan"
                >
                    <x-heroicon-o-moon class="h-5 w-5" data-tech-theme-icon="light" />
                    <x-heroicon-o-sun class="hidden h-5 w-5" data-tech-theme-icon="dark" />
                </button>
            </div>
        </header>
        <main class="mx-auto w-full max-w-6xl px-3 py-4 pb-24 md:px-5 md:py-6 md:pb-12">
            @if (session('status'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50/95 px-4 py-3 text-sm font-bold text-emerald-700 shadow-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50/95 px-4 py-3 text-sm text-rose-700 shadow-sm">
                    <strong class="font-black">Periksa data registrasi.</strong>
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
