<style>
    h1 { font-size: 28pt; color: #111827; }
    h2 { font-size: 15pt; color: #111827; }
    p, td { color: #374151; font-size: 10pt; }
    .muted { color: #6b7280; font-size: 9pt; }
    th { background-color: #111827; color: #ffffff; font-size: 9pt; font-weight: bold; }
</style>
<table cellpadding="0"><tr>
    <td width="60%"><h2>FASHION STORE</h2><span class="muted">{{ config('services.brevo.contact_recipient') }}</span></td>
    <td width="40%" align="right"><h1>INVOICE</h1><b>{{ $order->code }}</b><br/><span class="muted">Issued {{ $order->created_at->format('d M Y') }}</span></td>
</tr></table>
<br/><hr/><br/>
<table cellpadding="4"><tr>
    <td width="60%"><span class="muted">BILL TO</span><br/><b>{{ $order->customer_name }}</b><br/>{{ $order->customer_address }}<br/>{{ $order->customer_city }}<br/>{{ $order->customer_phone }}</td>
    <td width="40%"><span class="muted">ORDER DETAILS</span><br/>{{ $order->code }}<br/>Payment: Cash on delivery<br/>Currency: USD</td>
</tr></table>
<br/><br/>
<table cellpadding="8" cellspacing="0" border="0">
    <thead><tr>
        <th width="49%">PRODUCT</th><th width="12%" align="right">QTY</th><th width="19%" align="right">UNIT PRICE</th><th width="20%" align="right">AMOUNT</th>
    </tr></thead>
    <tbody>
    @foreach($order->items as $item)
        <tr nobr="true" bgcolor="{{ $loop->even ? '#f3f4f6' : '#ffffff' }}">
            <td width="49%">{{ $item->product_name }}@if($item->color)<br/><span class="muted">{{ $item->color }}</span>@endif</td>
            <td width="12%" align="right">{{ $item->quantity }}</td>
            <td width="19%" align="right">${{ number_format($item->unit_price, 2) }}</td>
            <td width="20%" align="right">${{ number_format(round($item->unit_price * 100) * $item->quantity / 100, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<br/>
<table cellpadding="10"><tr><td width="61%"></td><td width="39%" bgcolor="#f3f4f6" align="right"><b>TOTAL (USD)</b><br/><font size="18">${{ number_format($order->total, 2) }}</font></td></tr></table>
<br/>
<p class="muted">Payment method: cash on delivery. This invoice is an order bill, not confirmation of payment. Prices reflect the amounts agreed at checkout.</p>
<p><b>Thank you for shopping with Fashion Store.</b></p>
