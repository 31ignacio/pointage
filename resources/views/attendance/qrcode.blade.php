@extends('layouts.app')

@section('title', 'QR Code de pointage')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 text-center">
            <div class="card p-4" id="print-area">
                <div class="page-kicker">Accueil du personnel</div>
                <h1 class="h4 mb-3">QR Code de pointage</h1>
                <div class="d-flex justify-content-center my-3">
                    <img class="qr-code-image" src="{{ $qrImage }}" width="260" height="260" alt="QR Code de pointage">
                </div>
                <p>Scannez ce code pour enregistrer votre arrivée ou votre sortie.</p>
            </div>
            <button class="btn btn-primary mt-3 no-print" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimer
            </button>
        </div>
    </div>

    <style>
        @page {
            margin: 0;
        }

        @media print {
            body {
                margin: 0 !important;
            }

            body * {
                visibility: hidden;
            }

            #print-area,
            #print-area * {
                visibility: visible;
            }

            .navbar,
            .no-print {
                display: none !important;
            }

            #print-area {
                position: absolute;
                top: 0;
                left: 0;
                display: flex;
                width: 100%;
                min-height: 100vh;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 12mm !important;
                overflow: visible;
                box-shadow: none !important;
                border: none !important;
            }

            #print-area .qr-code-image {
                width: 100mm;
                height: 100mm;
                max-width: 90vw;
                object-fit: contain;
            }
        }
    </style>
@endsection
