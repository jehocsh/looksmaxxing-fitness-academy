<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Your LooksMaxxing application</title></head>
<body style="font-family:Arial,sans-serif;background:#eeedeb;color:#211d1b;padding:30px">
<div style="max-width:560px;margin:auto;background:white;padding:32px;border-top:6px solid #4756d7">
<p style="font-weight:bold;letter-spacing:2px">LOOKSMAXXING / FITNESS ACADEMY</p>
<h1>You're one step closer.</h1><p>Hello {{ $transaction->member->full_name }},</p>
<p>We've received your membership application.</p>
<p><strong>Reference:</strong> {{ $transaction->reference_no }}<br><strong>Plan:</strong> {{ $transaction->level_name }}<br><strong>Amount due:</strong> ₱{{ number_format($transaction->total_cents / 100, 2) }}</p>
<p>Your application is pending payment and staff approval. Keep this reference and contact the academy to arrange payment.</p>
<p>LooksMaxxing Fitness Academy<br>Tondo, Manila</p>
</div></body></html>
