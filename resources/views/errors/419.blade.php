@extends('layouts.app')
@section('title', 'Session expirée')
@section('content')
    @include('errors._page', ['code' => '419', 'title' => 'Session expirée', 'message' => 'Votre session a expiré. Reconnectez-vous puis réessayez.', 'icon' => 'bi-clock-history'])
@endsection
