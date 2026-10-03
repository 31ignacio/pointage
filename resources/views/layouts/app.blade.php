<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pointage') — Gestion des présences</title>
    <meta name="theme-color" content="#101a33">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
   <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body>
    @auth
        <nav class="navbar navbar-expand-xl navbar-dark app-navbar">
            <div class="container-fluid page-shell">
                <a class="navbar-brand" href="{{ route('attendance.scan') }}"><span class="brand-mark"><i
                            class="bi bi-qr-code-scan"></i></span> Pointage</a>
                <button class="navbar-toggler" type="button" data-nav-toggle aria-controls="mainNav" aria-expanded="false"
                    aria-label="Ouvrir le menu"><span class="navbar-toggler-icon"></span></button>
                <div class="main-nav" id="mainNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="{{ route('attendance.scan') }}"><i
                                    class="bi bi-geo-alt me-1"></i>Pointer</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('attendance.history') }}"><i
                                    class="bi bi-clock-history me-1"></i>Mon historique</a></li>
                        @if (auth()->user()->isAdmin())
                            <li class="nav-item nav-group">
                                <button class="nav-link nav-group-toggle" type="button" data-submenu-toggle
                                    aria-expanded="false" aria-controls="adminMenu"><i
                                        class="bi bi-grid me-1"></i>Administration <i
                                        class="bi bi-chevron-down submenu-chevron"></i></button>
                                <div class="nav-submenu admin-submenu" id="adminMenu">
                                    <a href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2"></i><span><strong>Tableau
                                                de bord</strong><small>Vue d’ensemble des présences</small></span></a>
                                    <a href="{{ route('employees.index') }}"><i
                                            class="bi bi-people"></i><span><strong>Employés</strong><small>Comptes et
                                                historique</small></span></a>
                                    <a href="{{ route('sites.index') }}"><i
                                            class="bi bi-building"></i><span><strong>Sites</strong><small>Lieux de
                                                travail</small></span></a>
                                    <a href="{{ route('departments.index') }}"><i
                                            class="bi bi-diagram-3"></i><span><strong>Services</strong><small>Organisation
                                                de l’équipe</small></span></a>
                                    <a href="{{ route('attendance.qrcode') }}"><i class="bi bi-qr-code"></i><span><strong>QR
                                                Code</strong><small>Afficher ou imprimer le code</small></span></a>
                                </div>
                            </li>
                        @endif
                    </ul>
                    <div class="nav-account">
                        <a class="nav-link" href="{{ route('profile.password.edit') }}"><i
                                class="bi bi-person-gear me-1"></i>Mon profil</a>
                        <span class="navbar-text">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button
                                class="btn btn-outline-light btn-sm">Déconnexion</button></form>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    <main class="container-fluid page-shell page-content">
        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button
                    type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
        @endif
        @if (session('temp_password'))
            <div class="alert alert-warning"><strong>Compte créé pour
                    {{ session('temp_password_email') }}</strong><br>Mot de passe temporaire :
                <code>{{ session('temp_password') }}</code><br><small>À communiquer une seule fois à l’employé, puis à
                    changer.</small></div>
        @endif
        @yield('content')
    </main>
    @stack('scripts')
</body>

</html>
