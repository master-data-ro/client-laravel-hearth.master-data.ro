<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acces restricționat — Licență necesară</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5 text-center">
                        <div class="text-primary mb-3"><i class="bi bi-shield-lock display-4"></i></div>
                        <h1 class="h4 fw-bold mb-3">Acces restricționat</h1>
                        <p class="text-muted mb-4">{{ $message ?? 'Este necesară o licență valabilă pentru a utiliza această aplicație.' }}</p>
                        <a href="/licenta" class="btn btn-primary btn-lg px-4 rounded-pill">
                            <i class="bi bi-key-fill me-2"></i> Deschide pagina de licență
                        </a>
                        <p class="small text-muted mt-4 mb-0">Dacă această pagină apare din greșeală, contactați suportul tehnic.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
