@extends('layouts.app')

@section('title', 'Mon profil')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="page-heading">
                <div>
                    <div class="page-kicker">Compte sécurisé</div>
                    <h1 class="mb-1">Mon profil</h1>
                    <p class="text-muted mb-0">Gérez vos accès et votre mot de passe.</p>
                </div>
            </div>

            <div class="card p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                        style="width: 52px; height: 52px; font-size: 1.35rem;">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div>
                        <h2 class="h5 mb-1">Changer le mot de passe</h2>
                        <p class="text-muted small mb-0">Utilisez au moins 8 caractères et ne réutilisez pas un mot de passe
                            connu.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Mot de passe actuel</label>
                        <div class="input-group"><input id="current_password" type="password" name="current_password" class="form-control" required
                            autocomplete="current-password" data-password-toggle-input><button type="button" class="btn btn-outline-secondary" data-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="password">Nouveau mot de passe</label>
                            <div class="input-group"><input id="password" type="password" name="password" class="form-control" required
                                autocomplete="new-password" data-password-toggle-input><button type="button" class="btn btn-outline-secondary" data-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                            <div class="input-group"><input id="password_confirmation" type="password" name="password_confirmation"
                                class="form-control" required autocomplete="new-password" data-password-toggle-input><button type="button" class="btn btn-outline-secondary" data-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
