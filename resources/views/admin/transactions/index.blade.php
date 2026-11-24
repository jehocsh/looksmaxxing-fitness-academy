@extends('layouts.admin')
@section('title', 'Transactions · LooksMaxxing')
@section('admin-content')
<div class="page-heading"><div><p class="eyebrow">ACADEMY / TRANSACTIONS</p><h1>EVERY REP.<br><span>EVERY RECORD.</span></h1></div><a class="button" href="{{ route('members.index') }}">Choose a member ↗</a></div>
<form method="get" class="filter-bar" action="{{ route('transactions.index') }}"><div class="field"><label for="q">Search transactions</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Reference number or member name"></div><div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['pending', 'paid', 'approved', 'expired', 'cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div><button class="button" type="submit">Search ↗</button><a class="text-link" href="{{ route('transactions.index') }}">Reset</a></form>
@include('admin.transactions.table')
<div class="pagination-wrap">{{ $transactions->links('pagination::simple-default') }}</div>
@endsection
