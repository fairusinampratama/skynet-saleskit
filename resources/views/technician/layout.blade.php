<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SalesKit Technician' }}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6f7f9; color: #17202a; }
        a { color: inherit; text-decoration: none; }
        .shell { min-height: 100vh; }
        .topbar { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; background: #ffffff; border-bottom: 1px solid #e4e7ec; }
        .brand { font-weight: 750; }
        .content { width: min(860px, 100%); margin: 0 auto; padding: 18px 14px 36px; }
        .panel { background: #ffffff; border: 1px solid #e4e7ec; border-radius: 8px; padding: 16px; margin-bottom: 14px; }
        .section-title { margin: 0 0 12px; font-size: 15px; font-weight: 750; }
        .grid { display: grid; gap: 12px; }
        @media (min-width: 720px) { .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        label { display: grid; gap: 6px; font-size: 13px; font-weight: 650; color: #344054; }
        input, select, textarea { width: 100%; border: 1px solid #cfd6df; border-radius: 8px; padding: 11px 12px; font: inherit; background: #ffffff; color: #17202a; }
        textarea { min-height: 92px; resize: vertical; }
        .button-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .btn { display: inline-flex; justify-content: center; align-items: center; gap: 8px; min-height: 42px; border: 0; border-radius: 8px; padding: 10px 14px; font: inherit; font-weight: 700; cursor: pointer; }
        .btn.primary { background: #b7791f; color: #ffffff; }
        .btn.secondary { background: #e9edf3; color: #17202a; }
        .btn.danger { background: #b42318; color: #ffffff; }
        .status { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 9px; background: #eef2f6; font-size: 12px; font-weight: 750; text-transform: capitalize; }
        .notice { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; background: #ecfdf3; color: #05603a; border: 1px solid #abefc6; }
        .errors { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; background: #fff1f3; color: #b42318; border: 1px solid #fecdd6; }
        .list { display: grid; gap: 10px; }
        .item { display: grid; gap: 6px; padding: 14px; border: 1px solid #e4e7ec; border-radius: 8px; background: #ffffff; }
        .muted { color: #667085; font-size: 13px; }
        .camera { display: grid; gap: 10px; }
        .preview { width: 100%; aspect-ratio: 1.58 / 1; border-radius: 8px; border: 1px dashed #98a2b3; object-fit: cover; background: #eef2f6; }
        .frame { position: relative; overflow: hidden; border-radius: 8px; background: #111827; }
        video { display: block; width: 100%; aspect-ratio: 1.58 / 1; object-fit: cover; }
        .frame::after { content: ""; position: absolute; inset: 10%; border: 2px solid #facc15; border-radius: 8px; box-shadow: 0 0 0 999px rgb(0 0 0 / 35%); }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a class="brand" href="{{ route('technician.registrations.index') }}">SalesKit</a>
            @auth
                <form method="POST" action="{{ route('technician.logout') }}">
                    @csrf
                    <button class="btn secondary" type="submit">Logout</button>
                </form>
            @endauth
        </header>
        <main class="content">
            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="errors">
                    <strong>Check the registration data.</strong>
                    <ul>
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
