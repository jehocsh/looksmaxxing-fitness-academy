@extends('layouts.admin')
@section('title', 'Academy overview · LooksMaxxing')
@section('admin-content')
<section>
    <div class="admin-heading"><div><p class="eyebrow">ACADEMY / OVERVIEW</p><h1>LET'S GET<br><span>TO WORK.</span></h1><p>Welcome back, {{ auth()->user()->name }}. Here's the academy at a glance.</p></div><a class="button" href="{{ route('members.create') }}">Register member ↗</a></div>
    <div class="stat-grid"><article><span>Total members</span><strong>{{ number_format($totalMembers) }}</strong><small>EVERY JOURNEY COUNTS.</small></article><article><span>Active members</span><strong>{{ number_format($activeMembers) }}</strong><small>CURRENT MEMBERSHIPS</small></article><article><span>Pending transactions</span><strong>{{ number_format($pendingTransactions) }}</strong><small>AWAITING PAYMENT</small></article><article class="stat-blue"><span>Payments collected</span><strong>₱{{ number_format($collectedCents / 100, 2) }}</strong><small>RECORDED PAYMENTS</small></article></div>
    <div class="section-heading compact"><h2>LATEST TRANSACTIONS</h2><span class="pill">{{ now()->format('d M Y') }}</span></div>
    @include('admin.transactions.table')
    <p><a class="text-link" href="{{ route('transactions.index') }}">All transactions ↗</a></p>
    <div class="section-heading compact"><h2>MEMBERSHIP LINEUP</h2><a href="{{ route('home') }}#plans">View public plans ↗</a></div>
    <div class="lineup-grid">@foreach($levels as $level)<article><span>{{ $level->name }}</span><strong>₱{{ number_format($level->fee_cents / 100, 0) }}</strong><small>{{ $level->validity_days }} DAYS</small></article>@endforeach</div>
</section>
@endsection
