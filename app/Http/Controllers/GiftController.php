<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GiftController extends Controller
{
    /**
     * Downloadable, printable version of the voucher shown at the end of
     * the /auguri-nadia quiz - a public page (it's a birthday link, not an
     * account feature), so this stays unauthenticated too.
     */
    public function auguriNadiaVoucher(): HttpResponse
    {
        $pdf = Pdf::loadView('gifts.auguri-nadia-voucher');

        return $pdf->download('voucher-spa-nadia.pdf');
    }
}
