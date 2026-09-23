@extends('layouts.app')

@section('title', 'QR Code de pointage')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 text-center">
            <div class="card p-4" id="print-area">
                <div class="page-kicker">Accueil du personnel</div>
                <h1 class="h4 mb-3">QR Code de pointage</h1>
                <div class="d-flex justify-content-center my-3">
                    <img src="{{ $qrImage }}" width="260" height="260" alt="QR Code de pointage">
                </div>
                <p class="text-muted small">{{ $scanUrl }}</p>
                <p>Scannez ce code pour enregistrer votre arrivée ou votre sortie.</p>
            </div>
            <button class="btn btn-primary mt-3 no-print" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimer
            </button>
        </div>
    </div>

    <style>
        @media print {

            .navbar,
            .no-print {
                display: none !important;
            }

            #print-area {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
@endsection
