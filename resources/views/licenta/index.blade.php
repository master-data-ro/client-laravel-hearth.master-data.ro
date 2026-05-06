@extends('license-client::licenta.layout')

@section('title', 'Management licență')

@section('content')
    @php
        use Hearth\LicenseClient\Messages;
        $hasLicense = !empty($license);
        $data = $hasLicense ? ($license['data'] ?? []) : [];
        $serverValid = !empty($data['valid']);
        $pending = !empty($data['pending']);
        $msg = $data['message'] ?? null;
        $inAprobare = $msg && str_contains(mb_strtolower((string) $msg), 'aprobare');
        $keyDisplay = $hasLicense ? ($license['license_key'] ?? '—') : '—';
        $domainDisplay = $hasLicense ? ($license['domain'] ?? '—') : '—';
        $code = $enforcement['code'] ?? 'missing';
        $enforcementOk = (bool) ($enforcement['ok'] ?? false);
        $enforcementTitle = Messages::portalEnforcementTitle($code);
        $enforcementDesc = Messages::portalEnforcementDescription($code);
        $validUntilFmt = null;
        if (!empty($validUntil)) {
            try {
                $validUntilFmt = \Illuminate\Support\Carbon::parse($validUntil)->timezone(config('app.timezone'))->format('d.m.Y H:i');
            } catch (\Throwable $e) {
                $validUntilFmt = $validUntil;
            }
        }
        $technicalBody = "Instalare: ".($siteUrl ?: $siteHost)."\nDomeniu configurat: {$siteHost}\n";
        if (!empty($fingerprintSummary)) {
            $technicalBody .= "Identificator tehnic (amprentă): {$fingerprintSummary}\n";
        }
        $technicalBody .= "Aplicație: ".config('app.name', 'Laravel')."\n";
    @endphp

    <div class="mb-4 pb-lg-1">
        <h1 class="h3 fw-semibold text-dark mb-2">Management licență</h1>
        <p class="text-muted mb-0 col-lg-10">
            <strong>Solicitarea</strong> pe domeniul din <code class="small">APP_URL</code> se poate face din această pagină (un clic) sau din SSH cu <code class="small">php artisan make:license-server</code>. La prima solicitare se generează automat o cheie provizorie; dacă există deja o cerere salvată, același buton <strong>actualizează stadiul</strong> de la autoritate. Introduceți cheia emisă definitiv în secțiunea de activare când o primiți.
        </p>
    </div>

    @if (session('license_gate_message'))
        <div class="alert alert-primary border-0 rounded-2 shadow-sm d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong class="d-block mb-1">Accesul la aplicație necesită licență validă.</strong>
                <span class="small">{{ session('license_gate_message') }}</span>
            </div>
        </div>
    @endif

    @if (session('license_error'))
        <div class="alert alert-danger border-0 rounded-2 shadow-sm d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-1"></i>
            <div>{{ session('license_error') }}</div>
        </div>
    @endif

    @if (session('license_success'))
        <div class="alert {{ ($hasLicense && $serverValid) ? 'alert-success' : 'alert-warning' }} border-0 rounded-2 shadow-sm d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi {{ ($hasLicense && $serverValid) ? 'bi-check-circle-fill' : 'bi-hourglass-split' }} flex-shrink-0 mt-1"></i>
            <div>{{ session('license_success') }}</div>
        </div>
    @endif

    @if (!empty($error))
        <div class="alert alert-warning border-0 rounded-2 mb-3">{{ $error }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="lp-card h-100">
                <div class="lp-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h2 class="h6 mb-0 fw-semibold text-uppercase text-muted" style="letter-spacing:.06em;">Stare licență</h2>
                    @if ($hasLicense && $serverValid && $enforcementOk)
                        <span class="badge bg-success lp-badge-soft rounded-pill px-3 py-2"><i class="bi bi-patch-check me-1"></i>Activă</span>
                    @elseif ($hasLicense && $serverValid && !$enforcementOk)
                        <span class="badge bg-warning text-dark lp-badge-soft rounded-pill px-3 py-2"><i class="bi bi-shield-exclamation me-1"></i>Verificare locală</span>
                    @elseif ($pending || $inAprobare)
                        <span class="badge bg-info text-dark lp-badge-soft rounded-pill px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>În așteptare</span>
                    @elseif ($hasLicense && !$serverValid)
                        <span class="badge bg-warning text-dark lp-badge-soft rounded-pill px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i>Neactivă</span>
                    @else
                        <span class="badge bg-secondary lp-badge-soft rounded-pill px-3 py-2"><i class="bi bi-dash-circle me-1"></i>Neinstalată</span>
                    @endif
                </div>
                <div class="card-body p-4">
                    <h3 class="h5 fw-semibold mb-2">{{ $enforcementTitle }}</h3>
                    <p class="text-muted mb-3">{{ $enforcementDesc }}</p>

                    <div class="rounded-2 border bg-light px-3 py-2 mb-4 small">
                        <span class="text-muted text-uppercase fw-semibold me-2">Stadiu</span>
                        @if ($enforcementOk)
                            <span class="text-success fw-semibold">Licență activă — aplicația poate rula.</span>
                        @elseif ($hasLicense && ($pending || $inAprobare))
                            <span class="text-primary fw-semibold">În așteptare la autoritate</span> — cererea pe domeniu este înregistrată; reîncercați „Actualizare stadiu” periodic.
                        @elseif ($hasLicense && $serverValid && !$enforcementOk)
                            <span class="text-warning fw-semibold">Neconcordanță locală</span> — datele de la server nu trec verificarea acestei instalări (ex. domeniu).
                        @elseif ($hasLicense && !$serverValid)
                            <span class="text-warning fw-semibold">Licență neactivă sau respinsă</span> — verificați mesajul de la server sau solicitați din nou.
                        @else
                            <span class="text-secondary fw-semibold">Fără cerere salvată</span> — folosiți butonul „Solicită licența pe domeniu” de mai jos (sau CLI).
                        @endif
                    </div>

                    @if ($hasLicense)
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="lp-kpi h-100">
                                    <div class="text-muted small fw-semibold text-uppercase mb-1">Cheie înregistrată</div>
                                    <code class="user-select-all text-break d-block">{{ \Illuminate\Support\Str::limit($keyDisplay, 64) }}</code>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="lp-kpi h-100">
                                    <div class="text-muted small fw-semibold text-uppercase mb-1">Domeniu asociat</div>
                                    <div class="fw-medium">{{ $domainDisplay }}</div>
                                </div>
                            </div>
                        </div>

                        @if ($validUntilFmt)
                            <p class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i>Valabilitate indicată: <strong>{{ $validUntilFmt }}</strong></p>
                        @endif

                        @if ($msg && (!$serverValid || $pending))
                            <div class="alert alert-light border rounded-2 small mb-0">
                                <span class="text-muted text-uppercase fw-semibold me-2">Răspuns server</span>{{ $msg }}
                            </div>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            @if ($hasLicense && $serverValid && $enforcementOk)
                                <div class="alert alert-success border-0 mb-0 py-2 px-3 small flex-grow-1 rounded-2">
                                    <i class="bi bi-check2-circle me-1"></i> Licența este instalată și acceptată de această aplicație.
                                </div>
                            @else
                                @if ($hasLicense && (!$serverValid || $pending || $inAprobare || ($serverValid && !$enforcementOk)))
                                    <form method="POST" action="{{ route('license-client.licenta.destroy') }}" class="d-inline" onsubmit="return confirm('Ștergeți datele locale de licență? Veți putea introduce o altă cheie.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-trash me-1"></i> Resetează instalarea</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @else
                        <div class="rounded-2 border border-dashed p-4 text-center bg-light">
                            <i class="bi bi-inbox text-muted fs-2 d-block mb-2"></i>
                            <p class="text-muted small mb-0">Nu există încă cerere salvată. Apăsați <strong>Solicită licența pe domeniu</strong> în secțiunea următoare (sau folosiți CLI). După ce primiți cheia emisă, folosiți <strong>Activare cu cheie</strong>.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="lp-card h-100">
                <div class="lp-card-header">
                    <h2 class="h6 mb-0 fw-semibold text-uppercase text-muted" style="letter-spacing:.06em;">Flux recomandat</h2>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="lp-step">
                        <span class="lp-step-num">1</span>
                        <div>
                            <div class="fw-semibold small">Solicitare pe domeniu</div>
                            <div class="text-muted small mb-2">Din pagină: <strong>Solicită licența pe domeniu</strong> (domeniul din <code class="small">APP_URL</code>; cheie provizorie generată automat la prima solicitare).</div>
                            <pre class="bg-light border rounded-2 p-2 small mb-0 user-select-all" style="white-space: pre-wrap; word-break: break-all;">php artisan make:license-server cheie-random</pre>
                        </div>
                    </div>
                    <div class="lp-step">
                        <span class="lp-step-num">2</span>
                        <div>
                            <div class="fw-semibold small">Urmărire stadiu</div>
                            <div class="text-muted small">Același buton <strong>Actualizare stadiu</strong> reîntreabă autoritatea cu cheia deja salvată. În fundal, dacă aveți <code class="small">schedule:run</code> în cron, pachetul rulează <code class="small">license-client:sync</code> la fiecare 5 minute. Stadiul apare în panoul din stânga.</div>
                        </div>
                    </div>
                    <div class="lp-step">
                        <span class="lp-step-num">3</span>
                        <div>
                            <div class="fw-semibold small">Activare cu cheia emisă</div>
                            <div class="text-muted small">După primirea cheii definitive: <code class="small">make:license-server CHEIE</code> sau formularul <strong>Activare cu cheie</strong>.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="lp-card mb-4">
        <div class="lp-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 mb-0 fw-semibold text-uppercase text-muted" style="letter-spacing:.06em;">Solicitare pe domeniu</h2>
            @if (! $enforcementOk)
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Domeniu din APP_URL</span>
            @endif
        </div>
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <p class="text-muted small mb-3 mb-lg-0">
                        @if ($hasLicense && ($license['license_key'] ?? null))
                            Există deja o <strong>cheie asociată</strong> acestei instalări. Apăsați butonul pentru a <strong>actualiza stadiul</strong> de la autoritate (același flux ca <code class="small">make:license-server</code> cu cheia salvată).
                        @else
                            Nu există cerere salvată. Un clic trimite către autoritate <strong>domeniul</strong> <code class="small">{{ $siteHost }}</code> și o <strong>cheie provizorie generată automat</strong> pe server.
                        @endif
                    </p>
                </div>
                <div class="col-lg-5">
                    @if ($enforcementOk)
                        <p class="text-muted small mb-0">Licența este activă; solicitarea nu mai este necesară.</p>
                    @else
                        <form method="POST" action="{{ route('license-client.licenta.solicita') }}" class="d-grid gap-2">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-lg py-3 shadow-sm">
                                <i class="bi bi-send-check me-2"></i>
                                @if ($hasLicense && ($license['license_key'] ?? null))
                                    Actualizare stadiu de la autoritate
                                @else
                                    Solicită licența pe domeniu
                                @endif
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <hr class="text-muted opacity-25 my-4">
            <div class="row g-3 small text-muted">
                <div class="col-md-4">
                    <div class="text-uppercase fw-semibold mb-1">Domeniu trimis</div>
                    <div class="font-monospace fw-medium text-dark">{{ $siteHost }}</div>
                </div>
                @if ($siteUrl)
                    <div class="col-md-8">
                        <div class="text-uppercase fw-semibold mb-1">APP_URL</div>
                        <code class="user-select-all text-break d-block small">{{ $siteUrl }}</code>
                    </div>
                @endif
                @if ($fingerprintSummary)
                    <div class="col-12">
                        <div class="text-uppercase fw-semibold mb-1">Amprentă (opțional)</div>
                        <code class="small user-select-all text-break d-block">{{ $fingerprintSummary }}</code>
                    </div>
                @endif
                <div class="col-12">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="lp-copy-tech">
                        <i class="bi bi-clipboard me-1"></i> Copiază identificatori instalare
                    </button>
                    <textarea id="lp-tech-raw" class="d-none" readonly>{{ $technicalBody }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="lp-card mb-2">
        <div class="lp-card-header">
            <h2 class="h6 mb-0 fw-semibold text-uppercase text-muted" style="letter-spacing:.06em;">Activare cu cheie</h2>
        </div>
        <div class="card-body p-4">
            @if ($hasLicense && $serverValid && $enforcementOk)
                <p class="text-muted small mb-0">Licența este activă. Pentru o cheie nouă, folosiți <strong>Resetează instalarea</strong> în zona de status.</p>
            @elseif ($hasLicense && $serverValid && !$enforcementOk)
                <div class="alert alert-warning border-0 rounded-2 small mb-0">
                    Răspunsul serverului indică licență activă, dar verificarea locală eșuează (de ex. domeniu sau expirare). Remediați configurarea sau folosiți <strong>Resetează instalarea</strong>, apoi introduceți o cheie potrivită acestei instalări.
                </div>
            @else
                <form method="POST" action="{{ route('license-client.licenta.upload') }}" class="needs-validation" novalidate>
                    @csrf
                    <div class="form-floating mb-3">
                        <textarea class="form-control" name="license_key" id="license_key" placeholder="Cheie" style="min-height: 6.5rem" required>{{ old('license_key') }}</textarea>
                        <label for="license_key">Cheie de licență</label>
                    </div>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-cloud-check me-2"></i> Verifică și salvează
                    </button>
                    <p class="text-muted small mt-3 mb-0">Datele sunt transmise către serverul de licențiere și salvate criptat local, conform politicii furnizorului.</p>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var raw = document.getElementById('lp-tech-raw');
    var btn = document.getElementById('lp-copy-tech');
    if (!btn || !raw) return;
    btn.addEventListener('click', function () {
        var text = raw.value.trim();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i> Copiat';
                setTimeout(function () { btn.innerHTML = old; }, 2000);
            });
        } else {
            raw.classList.remove('d-none');
            raw.style.cssText = 'position:fixed;width:1px;height:1px;opacity:0';
            raw.select();
            try { document.execCommand('copy'); } catch (e) {}
            raw.classList.add('d-none');
        }
    });
})();
</script>
@endpush
