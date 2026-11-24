@extends('layouts.site')
@section('title', 'Apply for membership · LooksMaxxing')
@section('content')
<section class="application-section">
<div class="page-heading"><div><p class="eyebrow">LOOKSMAXXING / YOUR FIRST REP</p><h1>MAKE THE<br><span>FIRST MOVE.</span></h1><p>Choose your membership. We'll help you get started.</p></div><a class="text-link" href="{{ route('home') }}#plans">Compare plans ↗</a></div>
@if(session('application_reference'))<div class="application-success" role="status"><p class="eyebrow">APPLICATION RECEIVED</p><h2>You're on the list.</h2><p>Keep your reference number: <strong class="reference-cell">{{ session('application_reference') }}</strong></p><p>Amount due: <strong>₱{{ number_format(session('application_total') / 100, 2) }}</strong>. Contact the academy to arrange cash, GCash, or bank transfer payment. Membership starts after full payment and staff approval.</p></div>@endif
@if($errors->any())<div class="error-box" role="alert"><strong>Please check your application.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="form-card" method="post" action="{{ route('membership.store') }}">@csrf
<h2>Your details</h2>
@include('membership.fields')
<h2 class="form-subheading">Location <span class="muted">(optional)</span></h2>
<p class="muted">The map starts in Tondo. Click to select an area, or enter coordinates below. You can submit without sharing coordinates.</p>
@include('membership.location', ['editable' => true, 'latitude' => old('latitude'), 'longitude' => old('longitude')])
<label class="checkbox-field"><input type="checkbox" name="consent" value="1" @checked(old('consent')) required><span>I agree to the academy storing my application details and any location I choose for membership processing and contacting me about this application.</span></label>
<div class="form-actions"><button class="button" type="submit">Submit application ↗</button><small class="muted">Payment is arranged with staff after you apply.</small></div>
</form>
</section>
@endsection
