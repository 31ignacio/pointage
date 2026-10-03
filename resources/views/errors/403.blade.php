@extends('layouts.app')
@section('title', 'Accès interdit')
@section('content')
    @include('errors._page', ['code' => '403', 'title' => 'Accès interdit', 'message' => 'Vous n’avez pas les autorisations nécessaires pour consulter cette page.', 'icon' => 'bi-shield-lock'])
@endsection
