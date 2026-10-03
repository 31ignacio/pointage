@extends('layouts.app')
@section('title', 'Service indisponible')
@section('content')
    @include('errors._page', ['code' => '503', 'title' => 'Service indisponible', 'message' => 'Le service est momentanément indisponible. Veuillez réessayer dans quelques instants.', 'icon' => 'bi-tools'])
@endsection
