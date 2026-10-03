@extends('layouts.app')
@section('title', 'Page introuvable')
@section('content')
    @include('errors._page', ['code' => '404', 'title' => 'Page introuvable', 'message' => 'La page demandée n’existe pas ou a été déplacée.', 'icon' => 'bi-search'])
@endsection
