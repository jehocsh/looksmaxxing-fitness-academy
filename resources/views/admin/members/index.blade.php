@extends('layouts.admin')
@section('title', 'Members · LooksMaxxing')
@section('admin-content')
<div class="page-heading"><div><p class="eyebrow">ACADEMY / MEMBERS</p><h1>THE PEOPLE.<br><span>THE PROGRESS.</span></h1></div><a class="button" href="{{ route('members.create') }}">Register member ↗</a></div>
<form class="filter-bar" method="get" action="{{ route('members.index') }}">
    <div class="field"><label for="q">Search members</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Name, email, or membership number"></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="field"><label for="level">Level</label><select id="level" name="level"><option value="">All levels</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected(request('level') == $level->id)>{{ $level->name }}</option>@endforeach</select></div>
    <button type="submit" class="button">Search ↗</button><a href="{{ route('members.index') }}" class="text-link">Reset</a>
</form>
<div class="table-wrap"><table><thead><tr><th>Member</th><th>Membership no.</th><th>Category / Level</th><th>Status</th><th>Expires</th><th></th></tr></thead><tbody>
@forelse($members as $member)
<tr><td><strong>{{ $member->full_name }}</strong><small class="table-secondary">{{ $member->email }}</small></td><td class="reference-cell">{{ $member->membership_no }}</td><td>{{ $member->category->name }}<small class="table-secondary">{{ $member->level->name }}</small></td><td><span class="pill status-{{ strtolower($member->membershipStatus()) }}">{{ $member->membershipStatus() }}</span></td><td>{{ $member->membership_expires_at?->format('d M Y') ?? 'Not activated' }}</td><td><a class="text-link" href="{{ route('members.show', $member) }}">View ↗</a></td></tr>
@empty<tr><td colspan="6" class="empty-state"><strong>No members found.</strong><p>Register a member or adjust your search.</p></td></tr>@endforelse
</tbody></table></div>
<div class="pagination-wrap">{{ $members->links('pagination::simple-default') }}</div>
@endsection
