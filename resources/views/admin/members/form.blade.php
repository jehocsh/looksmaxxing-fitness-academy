@extends('layouts.admin')
@section('title', ($member->exists ? 'Edit member' : 'Register member').' · LooksMaxxing')
@section('admin-content')
<div class="page-heading"><div><p class="eyebrow">ACADEMY / MEMBERS</p><h1>{{ $member->exists ? 'KEEP IT' : 'A NEW' }}<br><span>{{ $member->exists ? 'CURRENT.' : 'BEGINNING.' }}</span></h1></div><a class="text-link" href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}">← Back to members</a></div>
<form class="form-card" method="post" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}">
@csrf @if($member->exists) @method('PUT') @endif
@include('membership.fields', ['member' => $member])
<div class="form-actions"><button type="submit" class="button">{{ $member->exists ? 'Save changes' : 'Register member' }} ↗</button><span class="muted">New memberships require payment and approval.</span></div>
</form>
@endsection
