<?php

namespace App\Services;

use App\Models\Order;

class OrderInvoice
{
    public function render(Order $order): string
    {
        $order->loadMissing('items');
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);
        $pdf->setFooterFont(['dejavusans', '', 8]);
        $pdf->SetCreator('Fashion Store');
        $pdf->SetAuthor('Fashion Store');
        $pdf->SetTitle('Invoice '.$order->code);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();
        // All customer and product content is escaped by Blade. No remote assets.
        $pdf->writeHTML(view('invoices.order', ['order' => $order])->render());
        return $pdf->Output('', 'S');
    }
}
