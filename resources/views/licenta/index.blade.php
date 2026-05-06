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
            Această pagină vă permite să solicitați emiterea unei licențe, să urmăriți starea instalării și să introduceți cheia primită de la furnizorul software.
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
                    <p class="text-muted mb-4">{{ $enforcementDesc }}</p>

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
                                @if ($hasLicense)
                                    <form method="POST" action="{{ route('license-client.licenta.verify') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-arrow-repeat me-1"></i> Re-verificare la server</button>
                                    </form>
                                @endif
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
                            <p class="text-muted small mb-0">Nu există încă fișier de licență salvat pe acest server. După ce primiți cheia, folosiți secțiunea <strong>Activare cu cheie</strong> de mai jos.</p>
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
                            <div class="fw-semibold small">Solicitare</div>
                            <div class="text-muted small">Trimiteți furnizorului datele instalării (domeniu, identificatori).</div>
                        </div>
                    </div>
                    <div class="lp-step">
                        <span class="lp-step-num">2</span>
                        <div>
                            <div class="fw-semibold small">Emitere</div>
                            <div class="text-muted small">Primiți cheia de licență pe canalul agreat (e-mail, tichet).</div>
                        </div>
                    </div>
                    <div class="lp-step">
                        <span class="lp-step-num">3</span>
                        <div>
                            <div class="fw-semibold small">Activare</div>
                            <div class="text-muted small">Introduceți cheia mai jos; aplicația se deblochează după validare.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="lp-card h-100">
                <div class="lp-card-header">
                    <h2 class="h6 mb-0 fw-semibold text-uppercase text-muted" style="letter-spacing:.06em;">Identificatori instalare</h2>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">Includeți acest bloc în solicitarea către furnizor pentru emiterea sau activarea licenței.</p>
                    <div class="lp-kpi mb-3">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Domeniu</div>
                            <div class="font-monospace fw-medium">{{ $siteHost }}</div>
                    </div>
                    @if ($siteUrl)
                        <div class="lp-kpi mb-3">
                            <div class="text-muted small fw-semibold text-uppercase mb-1">URL aplicație</div>
                            <code class="small user-select-all text-break d-block">{{ $siteUrl }}</code>
                        </div>
                    @endif
                    @if ($fingerprintSummary)
                        <div class="lp-kpi mb-3">
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Amprentă tehnică</div>
                            <code class="small user-select-all text-break d-block">{{ $fingerprintSummary }}</code>
                        </div>
                    @endif
                    <button type="button" class="btn btn-outline-primary btn-sm" id="lp-copy-tech">
                        <i class="bi bi-clipboard me-1"></i> Copiază textul pentru solicitare
                    </button>
                    <textarea id="lp-tech-raw" class="d-none" readonly>{{ $technicalBody }}</textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="lp-card h-100">
                <div class="lp-card-header">
                    <h2 class="h6 mb-0 fw-semibold text-uppercase text-muted" style="letter-spacing:.06em;">Solicitare licență</h2>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        Completați datele organizației, apoi deschideți mesajul precompletat către furnizor. Dacă nu aveți adresă dedicată, copiați identificatorii și folosiți canalul contractual (tichet, e-mail partener).
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase" for="lp-req-org">Organizație</label>
                        <input type="text" class="form-control form-control-sm" id="lp-req-org" placeholder="Denumire legală sau departament" autocomplete="organization">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase" for="lp-req-name">Persoană de contact</label>
                            <input type="text" class="form-control form-control-sm" id="lp-req-name" placeholder="Nume prenume" autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase" for="lp-req-email">E-mail contact</label>
                            <input type="email" class="form-control form-control-sm" id="lp-req-email" placeholder="nume@companie.ro" autocomplete="email">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase" for="lp-req-notes">Observații (opțional)</label>
                        <textarea class="form-control form-control-sm" id="lp-req-notes" rows="2" placeholder="Nr. contract, proiect, perioadă dorită…"></textarea>
                    </div>
                    @if ($licenseRequestEmail)
                        <button type="button" class="btn btn-primary w-100 mb-2" id="lp-open-mailto">
                            <i class="bi bi-envelope-paper me-2"></i> Deschide solicitarea în clientul de e-mail
                        </button>
                        <p class="text-muted small mb-0">Destinatar: <strong>{{ $licenseRequestEmail }}</strong> (setat prin variabila <code class="small">LICENSE_REQUEST_EMAIL</code> în mediul serverului).</p>
                    @else
                        <button type="button" class="btn btn-outline-secondary w-100 mb-2" id="lp-open-mailto" disabled title="Setați LICENSE_REQUEST_EMAIL pentru deschidere automată">
                            <i class="bi bi-envelope me-2"></i> E-mail către furnizor
                        </button>
                        <p class="text-muted small mb-0">
                            Pentru buton activ, administratorul poate defini în <code class="small">.env</code> variabila <code class="small">LICENSE_REQUEST_EMAIL</code> cu adresa furnizorului. Până atunci, folosiți <strong>Copiază textul pentru solicitare</strong> și trimiteți manual.
                        </p>
                    @endif
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
@php
    $mailtoSubject = 'Solicitare licență — ' . config('app.name', 'Aplicație');
@endphp
<script>
(function () {
    var raw = document.getElementById('lp-tech-raw');
    var mailBtn = document.getElementById('lp-open-mailto');
    var requestEmail = @json($licenseRequestEmail);

    function buildBody() {
        var org = document.getElementById('lp-req-org').value.trim();
        var name = document.getElementById('lp-req-name').value.trim();
        var email = document.getElementById('lp-req-email').value.trim();
        var notes = document.getElementById('lp-req-notes').value.trim();
        var lines = [];
        lines.push('Bună ziua,');
        lines.push('');
        lines.push('Solicităm emiterea / activarea licenței pentru următoarea instalare:');
        lines.push('');
        if (org) lines.push('Organizație: ' + org);
        if (name) lines.push('Persoană de contact: ' + name);
        if (email) lines.push('E-mail: ' + email);
        if (notes) lines.push('Observații: ' + notes);
        lines.push('');
        lines.push('— Date tehnice instalare —');
        lines.push(raw.value.trim());
        lines.push('');
        lines.push('Cu stimă');
        return lines.join('\n');
    }

    document.getElementById('lp-copy-tech').addEventListener('click', function () {
        var text = buildBody();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                var btn = document.getElementById('lp-copy-tech');
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i> Copiat';
                setTimeout(function () { btn.innerHTML = old; }, 2000);
            });
        } else {
            raw.classList.remove('d-none');
            raw.style.position = 'fixed';
            raw.style.height = '1px';
            raw.style.width = '1px';
            raw.value = text;
            raw.select();
            try { document.execCommand('copy'); } catch (e) {}
            raw.classList.add('d-none');
        }
    });

    if (mailBtn && requestEmail) {
        mailBtn.addEventListener('click', function () {
            var subject = encodeURIComponent(@json($mailtoSubject));
            var body = encodeURIComponent(buildBody());
            window.location.href = 'mailto:' + encodeURIComponent(requestEmail) + '?subject=' + subject + '&body=' + body;
        });
    }
})();
</script>
@endpush
