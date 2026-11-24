@extends('layouts.admin')
@section('title', $member->full_name.' · LooksMaxxing')
@section('admin-content')
<div class="page-heading"><div><p class="eyebrow">ACADEMY / MEMBER PROFILE</p><h1 class="person-name">{{ $member->full_name }}</h1><span class="pill status-{{ strtolower($member->membershipStatus()) }}">{{ $member->membershipStatus() }}</span></div><div class="button-row"><a class="button button-outline" href="{{ route('members.edit', $member) }}">Edit details ↗</a><a class="text-link" href="{{ route('members.index') }}">All members</a></div></div>
<div class="detail-grid">
<article class="form-card"><h2>Member details</h2><dl class="facts"><dt>Membership number</dt><dd class="reference-cell">{{ $member->membership_no }}</dd><dt>Email</dt><dd>{{ $member->email }}</dd><dt>Mobile</dt><dd>{{ $member->phone ?: 'Not provided' }}</dd><dt>Address / area</dt><dd>{{ $member->address ?: 'Not provided' }}</dd><dt>Category</dt><dd>{{ $member->category->name }} · {{ $member->category->discount_percent }}% discount</dd><dt>Level</dt><dd>{{ $member->level->name }}</dd><dt>Activated</dt><dd>{{ $member->activated_at?->format('d M Y, g:i A') ?? 'Pending approval' }}</dd><dt>Expires</dt><dd>{{ $member->membership_expires_at?->format('d M Y, g:i A') ?? 'Not activated' }}</dd>@if($member->latitude !== null)<dt>Coordinates</dt><dd>{{ $member->latitude }}, {{ $member->longitude }}</dd>@endif</dl></article>
<article class="form-card"><h2>Membership actions</h2>
@php($openTransaction = $member->transactions->first(fn ($item) => in_array($item->status, ['pending', 'paid'])))
@if($openTransaction)<p>This member has a transaction awaiting payment or approval.</p><a class="button" href="{{ route('transactions.show', $openTransaction) }}">Open transaction ↗</a>
@elseif(!$member->is_deactivated)<p>{{ $member->activated_at ? 'Create a renewal using the current category and level. Active memberships keep their remaining days.' : 'Create a new application transaction using this member’s current category and level.' }}</p><form method="post" action="{{ $member->activated_at ? route('members.renew', $member) : route('members.reapply', $member) }}">@csrf<button class="button" type="submit">{{ $member->activated_at ? 'Create renewal' : 'Create application' }} ↗</button></form>@endif
<hr>
@if($member->is_deactivated)<p>Reactivation restores access to membership processing. Expired memberships still require renewal.</p><form method="post" action="{{ route('members.reactivate', $member) }}">@csrf<button class="button button-outline" type="submit">Reactivate member ↗</button></form>
@else<p>Deactivation preserves this member’s payment and transaction history.</p><form method="post" action="{{ route('members.destroy', $member) }}" data-confirm="Deactivate this member? Their history will be preserved.">@csrf @method('DELETE')<button class="button button-danger" type="submit">Deactivate member</button></form>@endif
</article></div>
<div class="section-heading compact"><h2>TRANSACTION HISTORY</h2></div>
@include('admin.transactions.table', ['transactions' => $member->transactions->sortByDesc('id')])
@endsection
