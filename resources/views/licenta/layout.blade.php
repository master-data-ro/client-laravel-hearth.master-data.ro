<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Licență') — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        :root { --tl-accent: #0d6efd; }
        body {
            min-height: 100vh;
            background: linear-gradient(165deg, #e8eef8 0%, #f4f7fb 45%, #ffffff 100%);
        }
        .tl-shell { max-width: 720px; margin: 0 auto; }
        .tl-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.35rem 1.25rem rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .tl-card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #3d8bfd 55%, #6ea8fe 100%);
            color: #fff;
            padding: 1.25rem 1.5rem;
            border: 0;
        }
        .tl-stat { border-radius: 0.75rem; background: #f8fafc; padding: 1rem 1.25rem; }
        .form-floating > label { color: #64748b; }
    </style>
    @stack('styles')
</head>
<body class="d-flex flex-column">
    <nav class="navbar navbar-dark bg-primary shadow-sm py-3">
        <div class="container tl-shell d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock fs-4"></i>
            <span class="navbar-brand mb-0 fw-semibold">Licență aplicație</span>
        </div>
    </nav>

    <main class="flex-grow-1 py-4 py-md-5">
        <div class="container tl-shell px-3">
            @yield('content')
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>
