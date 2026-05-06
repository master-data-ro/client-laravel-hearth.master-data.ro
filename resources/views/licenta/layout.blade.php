<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Licență') — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        :root {
            --lp-navy: #0f172a;
            --lp-navy-2: #1e293b;
            --lp-accent: #0b5ed7;
            --lp-accent-soft: rgba(11, 94, 215, 0.12);
            --lp-border: #e2e8f0;
            --lp-muted: #64748b;
        }
        body {
            min-height: 100vh;
            font-feature-settings: "kern" 1, "liga" 1;
            background: #f1f5f9;
            color: var(--lp-navy);
        }
        .lp-topbar {
            background: linear-gradient(105deg, var(--lp-navy) 0%, var(--lp-navy-2) 55%, #334155 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .lp-shell { max-width: 1040px; margin: 0 auto; }
        .lp-card {
            border: 1px solid var(--lp-border);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 24px rgba(15, 23, 42, 0.06);
        }
        .lp-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--lp-border);
            background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
            border-radius: 0.75rem 0.75rem 0 0;
        }
        .lp-kpi {
            border: 1px solid var(--lp-border);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
        }
        .lp-kpi code { font-size: 0.8125rem; color: var(--lp-navy-2); }
        .lp-step {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            padding: 0.65rem 0;
            border-bottom: 1px dashed var(--lp-border);
        }
        .lp-step:last-child { border-bottom: 0; }
        .lp-step-num {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 50%;
            background: var(--lp-accent-soft);
            color: var(--lp-accent);
            font-weight: 600;
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .lp-badge-soft {
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .lp-footer {
            border-top: 1px solid var(--lp-border);
            margin-top: auto;
            background: #fff;
        }
    </style>
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="lp-topbar text-white">
        <div class="container lp-shell py-3 px-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-2 bg-white bg-opacity-10 p-2 d-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
                        <i class="bi bi-shield-check text-white fs-5"></i>
                    </div>
                    <div>
                        <div class="text-white-50 small text-uppercase" style="letter-spacing:.08em;">Centru licențiere</div>
                        <div class="fw-semibold fs-5 lh-sm">{{ config('app.name', 'Aplicație') }}</div>
                    </div>
                </div>
                <div class="text-white-50 small text-md-end">
                    <i class="bi bi-building me-1"></i> Instalare securizată
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow-1 py-4 py-lg-5">
        <div class="container lp-shell px-3">
            @yield('content')
        </div>
    </main>

    <footer class="lp-footer py-3 mt-auto">
        <div class="container lp-shell px-3 text-center text-muted small">
            {{ config('app.name', 'Laravel') }} — gestionare licență locală. Datele cheii sunt procesate conform politicii furnizorului dumneavoastră.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>
