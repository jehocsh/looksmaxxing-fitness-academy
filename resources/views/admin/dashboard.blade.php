@extends('layouts.site')
@section('title', 'Academy overview · LooksMaxxing')
@section('content')
<section class="admin-section">
    <div class="admin-heading"><div><p class="eyebrow">ACADEMY / OVERVIEW</p><h1>LET'S GET<br><span>TO WORK.</span></h1><p>Welcome back, {{ auth()->user()->name }}. Here's the academy at a glance.</p></div><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button button-outline">Sign out ↗</button></form></div>
    <div class="stat-grid"><article><span>Total members</span><strong>{{ number_format($totalMembers) }}</strong><small>EVERY JOURNEY COUNTS.</small></article><article><span>Active members</span><strong>{{ number_format($activeMembers) }}</strong><small>CURRENT MEMBERSHIPS</small></article><article><span>Pending transactions</span><strong>{{ number_format($pendingTransactions) }}</strong><small>AWAITING PAYMENT</small></article><article class="stat-blue"><span>Payments collected</span><strong>₱{{ number_format($collectedCents / 100, 2) }}</strong><small>RECORDED PAYMENTS</small></article></div>
    <div class="section-heading compact"><h2>LATEST TRANSACTIONS</h2><span class="pill">{{ now()->format('d M Y') }}</span></div>
    <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Member</th><th>Type</th><th>Plan</th><th>Total</th><th>Status</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
        <tr><td>{{ $transaction->reference_no }}</td><td>{{ $transaction->member->full_name }}</td><td>{{ ucfirst($transaction->type) }}</td><td>{{ $transaction->level_name }}</td><td>₱{{ number_format($transaction->total_cents / 100, 2) }}</td><td><span class="pill">{{ ucfirst($transaction->status) }}</span></td></tr>
        @empty
        <tr><td colspan="6" class="empty-state"><strong>A fresh start.</strong><p>There are no membership transactions to display.</p></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="section-heading compact"><h2>MEMBERSHIP LINEUP</h2><a href="{{ route('home') }}#plans">View public plans ↗</a></div>
    <div class="lineup-grid">@foreach($levels as $level)<article><span>{{ $level->name }}</span><strong>₱{{ number_format($level->fee_cents / 100, 0) }}</strong><small>{{ $level->validity_days }} DAYS</small></article>@endforeach</div>
</section>
@endsection
