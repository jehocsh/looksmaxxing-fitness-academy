@extends('layouts.admin')
@section('title', 'Email status · LooksMaxxing')
@section('admin-content')
<div class="page-heading"><div><p class="eyebrow">ACADEMY / EMAIL STATUS</p><h1>KEEP IN<br><span>CONTACT.</span></h1><p>Check application confirmation emails and the connection to Brevo.</p></div><a class="button button-outline" href="{{ route('email-status') }}">Refresh status ↗</a></div>
<div class="detail-grid">
    <article class="form-card"><h2>Email service</h2><dl class="facts">
        <dt>API key</dt><dd>{{ $keyConfigured ? 'Configured' : 'Missing: add BREVO_API_KEY in EthioDeploy' }}</dd>
        <dt>Sender email</dt><dd>{{ $senderConfigured ? 'Configured' : 'Missing: add BREVO_SENDER_EMAIL in EthioDeploy' }}</dd>
        <dt>Brevo connection</dt><dd><strong>{{ $provider }}</strong></dd>
        <dt>Email processing</dt><dd>{{ $queueConnection }}</dd>
        <dt>Last accepted email</dt><dd>{{ $lastAccepted ?? 'None recorded' }}</dd>
    </dl><p class="muted">An accepted email has reached Brevo. Check Brevo's logs and the recipient's inbox to confirm delivery.</p></article>
    <article class="form-card"><h2>Application email queue</h2><dl class="facts">
        <dt>Queued jobs</dt><dd><strong>{{ $queuedJobs }}</strong></dd>
        <dt>Failed jobs</dt><dd><strong>{{ $failedJobs }}</strong></dd>
        <dt>Latest failure</dt><dd>{{ $lastFailure }}</dd>
    </dl><p>Pending emails should be picked up by the app's background worker. If this count stays above zero, check the start command and deployment logs.</p><p>In EthioDeploy Settings, the start command should be <code>sh start-ethiodeploy.sh</code>. Save any correction and redeploy.</p><p class="muted">This page only checks the connection and queue. Refreshing it does not send an email.</p></article>
</div>
@endsection
