<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderInvoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function customer(Request $request, string $code, OrderInvoice $invoice)
    {
        $order = Order::where('user_id', $request->user()->id)->where('code', $code)->firstOrFail();
        return $this->download($order, $invoice);
    }

    public function admin(int $id, OrderInvoice $invoice)
    {
        return $this->download(Order::findOrFail($id), $invoice);
    }

    private function download(Order $order, OrderInvoice $invoice)
    {
        $filename = 'Invoice-'.preg_replace('/[^a-zA-Z0-9-]/', '-', $order->code).'.pdf';
        return response($invoice->render($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
