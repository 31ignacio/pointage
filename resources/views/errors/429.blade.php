@extends('layouts.app')
@section('title', 'Trop de requêtes')
@section('content')
    @include('errors._page', ['code' => '429', 'title' => 'Trop de requêtes', 'message' => 'Un trop grand nombre de demandes a été reçu. Veuillez patienter avant de réessayer.', 'icon' => 'bi-hourglass-split'])
@endsection
