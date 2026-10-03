{{-- Variables : $code, $title, $message, $icon — optionnel : $accent (ex. '#d1344b') --}}
<section class="error-state mx-auto" role="alert" aria-labelledby="error-title"
         @isset($accent) style="--err-accent: {{ $accent }}" @endisset>

    <div class="error-visual" aria-hidden="true">
        <div class="badge-hang">
            <span class="badge-strap"></span>
            <div class="badge-card">
                <span class="badge-slot"></span>
                <span class="badge-icon"><i class="bi {{ $icon }}"></i></span>
                <span class="badge-code">{{ $code }}</span>
                <span class="badge-lines"><i></i><i></i></span>
                <span class="badge-beam"></span>
            </div>
        </div>
        <span class="badge-shadow"></span>
    </div>

    <div class="error-copy">
        <h1 id="error-title">{{ $title }}</h1>
        <p class="error-message">{{ $message }}</p>
        <p class="error-reference">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Code d’erreur {{ $code }}
        </p>

        <div class="error-actions">
            <a class="btn btn-primary error-home" href="{{ auth()->check() ? route('attendance.scan') : route('login') }}">
                <i class="bi bi-house-door me-2" aria-hidden="true"></i>Retour à l’accueil
            </a>
            <button class="btn btn-outline-secondary error-back" type="button" onclick="window.history.back()">
                Page précédente
            </button>
        </div>
    </div>
</section>