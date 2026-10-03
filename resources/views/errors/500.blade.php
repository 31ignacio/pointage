@extends('layouts.app')
@section('title', 'Erreur serveur')
@section('content')
    @include('errors._page', ['code' => '500', 'title' => 'Erreur serveur', 'message' => 'Une erreur inattendue est survenue. Veuillez réessayer plus tard.', 'icon' => 'bi-exclamation-triangle'])
@endsection
