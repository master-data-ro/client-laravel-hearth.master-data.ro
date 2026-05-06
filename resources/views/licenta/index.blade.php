@extends('license-client::licenta.layout')

@section('title', 'Activare licență')

@section('content')
    @php
        $hasLicense = !empty($license);
        $data = $hasLicense ? ($license['data'] ?? []) : [];
        $serverValid = !empty($data['valid']);
        $pending = !empty($data['pending']);
        $msg = $data['message'] ?? null;
        $inAprobare = $msg && str_contains(mb_strtolower((string) $msg), 'aprobare');
        $keyDisplay = $hasLicense ? ($license['license_key'] ?? '—') : '—';
        $domainDisplay = $hasLicense ? ($license['domain'] ?? '—') : '—';
    @endphp

    <div class="text-center mb-4">
        <h1 class="h3 fw-bold text-dark mb-1">Activare licență</h1>
        <p class="text-muted mb-0">Introduceți cheia primită. Dacă nu aveți o cheie, solicitați-o administratorului acestui sistem.</p>
    </div>

    @if (session('license_error'))
        <div class="alert alert-danger shadow-sm border-0 rounded-3 d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-1"></i>
            <div>{{ session('license_error') }}</div>
        </div>
    @endif

    @if (session('license_success'))
        <div class="alert {{ ($hasLicense && $serverValid) ? 'alert-success' : 'alert-warning' }} shadow-sm border-0 rounded-3 d-flex align-items-start gap-2" role="alert">
            <i class="bi {{ ($hasLicense && $serverValid) ? 'bi-check-circle-fill' : 'bi-hourglass-split' }} flex-shrink-0 mt-1"></i>
            <div>{{ session('license_success') }}</div>
        </div>
    @endif

    @if (!empty($error))
        <div class="alert alert-warning border-0 rounded-3 shadow-sm">{{ $error }}</div>
    @endif

    <div class="card tl-card mb-4">
        <div class="tl-card-header">
            <h2 class="h5 mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-activity"></i> Status
            </h2>
        </div>
        <div class="card-body p-4">
            @if (!$hasLicense)
                <div class="tl-stat text-center py-4 px-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:4rem;height:4rem;">
                        <i class="bi bi-inbox text-primary" style="font-size:1.75rem;"></i>
                    </div>
                    <div class="mb-2"><span class="badge text-bg-secondary rounded-pill px-3 py-2">Nu aveți licență instalată</span></div>
                    <p class="text-muted mb-0 small">Încă nu s-a salvat niciun fișier de licență valid. Folosiți formularul de mai jos după ce primiți cheia.</p>
                </div>
            @else
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="tl-stat h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Cheie</div>
                            <code class="small user-select-all text-break">{{ $keyDisplay }}</code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tl-stat h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Domeniu</div>
                            <div class="fw-medium">{{ $domainDisplay }}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="text-muted small text-uppercase fw-semibold me-1">Stare:</span>
                    @if ($inAprobare || $pending)
                        <span class="badge rounded-pill bg-info text-dark px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>În aprobare</span>
                    @elseif ($serverValid)
                        <span class="badge rounded-pill bg-success px-3 py-2"><i class="bi bi-patch-check-fill me-1"></i>Activă</span>
                    @else
                        <span class="badge rounded-pill bg-danger px-3 py-2"><i class="bi bi-x-octagon-fill me-1"></i>Nevalidă / inactivă</span>
                    @endif
                </div>

                @if (!empty($validUntil))
                    <p class="small text-muted mb-0"><i class="bi bi-calendar-event me-1"></i>Valabilitate indicată: <strong>{{ $validUntil }}</strong></p>
                @endif

                @if ($msg && (!$serverValid || $pending))
                    <div class="alert alert-light border mt-3 mb-0 small">{{ $msg }}</div>
                @endif

                <div class="d-flex flex-wrap gap-2 mt-4">
                    @if ($serverValid)
                        <div class="alert alert-success border-0 mb-0 py-2 px-3 small flex-grow-1">
                            <i class="bi bi-check2-circle me-1"></i> Licența este instalată și activă.
                        </div>
                    @elseif ($pending || $inAprobare)
                        <form method="POST" action="{{ route('license-client.licenta.verify') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Re-verifică</button>
                        </form>
                        <form method="POST" action="{{ route('license-client.licenta.destroy') }}" class="d-inline" onsubmit="return confirm('Ștergeți fișierul local de licență?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Șterge</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('license-client.licenta.destroy') }}" class="d-inline" onsubmit="return confirm('Ștergeți licența curentă pentru a introduce alta?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-trash me-1"></i> Șterge licența curentă</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="card tl-card">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h2 class="h5 mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-key-fill text-primary"></i> Introducere cheie
            </h2>
        </div>
        <div class="card-body px-4 pb-4">
            @if ($hasLicense && $serverValid)
                <p class="text-muted small mb-0">O licență activă este deja instalată. Pentru o cheie nouă, ștergeți mai întâi licența curentă din secțiunea de mai sus.</p>
            @else
                <form method="POST" action="{{ route('license-client.licenta.upload') }}" class="needs-validation" novalidate>
                    @csrf
                    <div class="form-floating mb-3">
                        <textarea class="form-control" name="license_key" id="license_key" placeholder="Cheie" style="height: 6rem" required>{{ old('license_key') }}</textarea>
                        <label for="license_key">Cheie de licență</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-cloud-check me-2"></i> Verifică și salvează
                    </button>
                    <p class="text-muted small mt-3 mb-0">După validare, datele sunt salvate criptat pe acest server.</p>
                </form>
            @endif
        </div>
    </div>
@endsection
