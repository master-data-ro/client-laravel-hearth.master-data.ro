@php
    $code = $license_code ?? 'unknown';
    $headline = \Hearth\LicenseClient\Messages::blockHeadline($code);
    $hint = \Hearth\LicenseClient\Messages::blockHint($code);
    $licentaUrl = route('license-client.licenta.index');
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }} — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        .bl-page {
            min-height: 100vh;
            background: radial-gradient(1200px 600px at 10% -10%, rgba(13, 110, 253, 0.18), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(111, 66, 193, 0.12), transparent 50%),
                linear-gradient(180deg, #f1f5f9 0%, #f8fafc 35%, #ffffff 100%);
        }
        .bl-orbit {
            width: 5.5rem;
            height: 5.5rem;
            border-radius: 50%;
            background: linear-gradient(145deg, #e7f1ff, #ffffff);
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.15), 0 0.5rem 1.5rem rgba(15, 23, 42, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            animation: bl-pulse 2.4s ease-in-out infinite;
        }
        @keyframes bl-pulse {
            0%, 100% { transform: scale(1); box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.15), 0 0.5rem 1.5rem rgba(15, 23, 42, 0.08); }
            50% { transform: scale(1.04); box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25), 0 0.75rem 2rem rgba(13, 110, 253, 0.12); }
        }
        .bl-card {
            border-radius: 1.25rem;
            border: 0;
            box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.1);
        }
        .bl-badge-code { font-size: 0.7rem; letter-spacing: 0.04em; }
    </style>
</head>
<body class="bl-page d-flex flex-column">
    <div class="py-2 bg-primary bg-opacity-10 border-bottom border-primary border-opacity-10">
        <div class="container small text-primary-emphasis d-flex align-items-center gap-2">
            <i class="bi bi-shield-exclamation"></i>
            <span>Protecție licență activă</span>
        </div>
    </div>

    <main class="flex-grow-1 d-flex align-items-center py-4 py-md-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-11 col-sm-10 col-md-9 col-lg-7 col-xl-6">
                    <div class="card bl-card overflow-hidden">
                        <div class="card-body p-4 p-md-5 text-center">
                            <div class="bl-orbit mb-4">
                                @if($code === 'missing')
                                    <i class="bi bi-key text-primary" style="font-size: 2.25rem;"></i>
                                @elseif($code === 'expired')
                                    <i class="bi bi-hourglass-bottom text-warning" style="font-size: 2.25rem;"></i>
                                @elseif($code === 'domain_mismatch')
                                    <i class="bi bi-globe2 text-danger" style="font-size: 2.25rem;"></i>
                                @else
                                    <i class="bi bi-shield-lock text-primary" style="font-size: 2.25rem;"></i>
                                @endif
                            </div>

                            <span class="badge rounded-pill text-bg-secondary bl-badge-code text-uppercase mb-2">{{ $code }}</span>
                            <h1 class="h3 fw-bold text-dark mb-2">{{ $headline }}</h1>
                            <p class="text-muted mb-3">{{ $hint }}</p>

                            <div class="alert alert-light border text-start small text-body-secondary rounded-3 mb-4" role="status">
                                <div class="d-flex gap-2">
                                    <i class="bi bi-info-circle flex-shrink-0 mt-1 text-primary"></i>
                                    <div>{{ $message ?? 'Este necesară o licență valabilă pentru a utiliza această aplicație.' }}</div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center flex-wrap">
                                <a href="{{ $licentaUrl }}" class="btn btn-primary btn-lg px-4 rounded-pill shadow-sm">
                                    <i class="bi bi-key-fill me-2"></i> Deschide activarea licenței
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-lg rounded-pill" data-bs-toggle="collapse" data-bs-target="#bl-steps" aria-expanded="false">
                                    <i class="bi bi-list-check me-2"></i> Pași rapizi
                                </button>
                            </div>

                            <div class="collapse text-start mt-4" id="bl-steps">
                                <div class="card card-body bg-light border-0 rounded-3 small">
                                    <ol class="mb-0 ps-3">
                                        <li class="mb-2">Deschideți pagina <strong>Activare licență</strong> (butonul de mai sus).</li>
                                        <li class="mb-2">Introduceți cheia primită de la administratorul sistemului.</li>
                                        <li class="mb-0">După salvare, reîncărcați pagina pe care doriți să o accesați.</li>
                                    </ol>
                                </div>
                            </div>

                            <p class="small text-muted mt-4 mb-0">
                                <i class="bi bi-question-circle me-1"></i>
                                Dacă credeți că este o eroare, contactați suportul tehnic și menționați codul <code class="small">{{ $code }}</code>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center text-muted small py-3">
        {{ config('app.name', 'Laravel') }} · acces limitat
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
