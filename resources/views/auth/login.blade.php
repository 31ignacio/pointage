<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101a33">
    <title>Connexion — Pointage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="login-page">
    <main class="login-layout">
        <section class="login-showcase" aria-label="Pointage du personnel">
            <div class="login-brand"><span class="brand-mark"><i class="bi bi-qr-code-scan"></i></span> Pointage</div>
            <div class="showcase-copy">
                <span class="eyebrow">Gestion des présences</span>
                <h4>Une équipe présente.Une journée bien lancée.</h4>
                <p>Retrouvez vos pointages et gérez les présences simplement, depuis le bureau ou votre téléphone.</p>
            </div>
            <div class="showcase-orbit orbit-one"></div>
            <div class="showcase-orbit orbit-two"></div>
            <div class="showcase-footer"><i class="bi bi-shield-check me-2"></i>Un accès sécurisé à votre espace de
                travail</div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <div class="login-mobile-brand"><span class="brand-mark"><i class="bi bi-qr-code-scan"></i></span>
                    Pointage</div>
                <span class="eyebrow">Bienvenue</span>
                <h2>Connectez-vous</h2>
                <p class="login-intro">Saisissez vos identifiants pour accéder à votre espace.</p>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert"><i
                            class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="user_id">Votre nom</label>
                        <div class="input-icon"><i class="bi bi-person"></i><select id="user_id" name="user_id" class="form-select" autocomplete="username" required autofocus>
                                <option value="">Sélectionnez votre nom</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mot de passe</label>
                        <div class="input-icon"><i class="bi bi-lock"></i><input id="password" type="password"
                                name="password" class="form-control" placeholder="Votre mot de passe"
                                autocomplete="current-password" data-password-toggle-input required><button type="button" class="password-toggle" data-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                    </div>
                    <label class="form-check remember-row mb-4"><input type="checkbox" name="remember"
                            class="form-check-input" id="remember"><span class="form-check-label">Se souvenir de
                            moi</span></label>
                    <button type="submit" class="btn btn-primary btn-lg w-100 login-submit">Se connecter <i
                            class="bi bi-arrow-right ms-2"></i></button>
                </form>
                <div class="login-help"><i class="bi bi-info-circle me-1"></i> Besoin d’aide ? Contactez votre
                    administrateur.</div>
            </div>
            <footer class="login-copyright">© {{ now()->year }} Pointage du personnel</footer>
        </section>
    </main>
</body>

</html>
