<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title }} — 2TONE</title>
    <style>
        :root {
            --bg: #0f1419;
            --card: #1a2332;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --radius: 16px;
            --shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg: #f4f6fb;
                --card: #ffffff;
                --text: #0f172a;
                --muted: #64748b;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(ellipse 120% 80% at 50% -20%, rgba(56, 189, 248, 0.15), transparent 50%),
                var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .wrap { width: 100%; max-width: 420px; }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 2rem 1.75rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.25rem;
        }
        .icon--success { background: rgba(52, 211, 153, 0.15); }
        .icon--warning { background: rgba(251, 191, 36, 0.15); }
        .icon--danger { background: rgba(248, 113, 113, 0.15); }
        .icon--info { background: rgba(129, 140, 248, 0.15); }
        h1 {
            font-size: 1.35rem;
            font-weight: 700;
            text-align: center;
            margin: 0 0 0.75rem;
            line-height: 1.3;
        }
        .sub {
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.55;
            margin: 0 0 1.25rem;
        }
        .meta {
            font-size: 0.8rem;
            color: var(--muted);
            text-align: center;
            margin-bottom: 1.25rem;
            font-variant-numeric: tabular-nums;
        }
        .note {
            margin: 0;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(148, 163, 184, 0.15);
            font-size: 0.85rem;
            color: var(--muted);
            text-align: center;
            line-height: 1.5;
        }
        .brand {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="icon icon--{{ $tone }}">
                @if($tone === 'success') ✓
                @elseif($tone === 'danger') ✕
                @elseif($tone === 'warning') !
                @else ℹ
                @endif
            </div>
            <h1>{{ $title }}</h1>
            <p class="sub">{{ $subtitle }}</p>
            @if(!empty($statusCode))
                <p class="meta">Statut : <strong>{{ $statusCode }}</strong></p>
            @endif
        </div>
        <p class="brand">2TONE</p>
    </div>
</body>
</html>
