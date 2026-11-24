@extends('layouts.site')
@section('content')
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span class="status-dot"></span> TONDO, MANILA · FITNESS ACADEMY</p>
        <h1>SHOW UP.<br><span>LEVEL UP.</span></h1>
        <p class="hero-description">Your next chapter starts with one rep. Find your rhythm, build your strength, and train with a community that shows up with you.</p>
        <a class="button button-orange" href="#plans">Find your membership <span aria-hidden="true">↗</span></a>
        <div class="hero-facts"><div><strong>4</strong><span>WAYS TO TRAIN</span></div><div><strong>30</strong><span>DAYS PER PLAN</span></div><div><strong>100%</strong><span>YOUR OWN PACE</span></div></div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <span class="art-label">LOOKSMAXXING / THE TRAINING CLUB</span>
        <svg viewBox="0 0 460 490" role="presentation">
            <defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-opacity=".1"/></pattern></defs>
            <rect width="460" height="490" fill="url(#grid)"/>
            <circle cx="230" cy="235" r="156" fill="none" stroke="#f1efed" stroke-width="2"/>
            <circle cx="230" cy="235" r="120" fill="none" stroke="#f1efed" stroke-width="2" stroke-dasharray="3 11"/>
            <g transform="rotate(-36 230 235)">
                <rect x="44" y="217" width="372" height="36" rx="9" fill="#211d1b"/>
                <rect x="75" y="141" width="54" height="188" rx="14" fill="#ee3537" stroke="#211d1b" stroke-width="8"/>
                <rect x="115" y="116" width="60" height="238" rx="14" fill="#f1efed" stroke="#211d1b" stroke-width="8"/>
                <rect x="285" y="116" width="60" height="238" rx="14" fill="#f1efed" stroke="#211d1b" stroke-width="8"/>
                <rect x="331" y="141" width="54" height="188" rx="14" fill="#e9831c" stroke="#211d1b" stroke-width="8"/>
                <path d="M185 230h90M185 240h90" stroke="#f1efed" stroke-opacity=".45" stroke-width="2"/>
            </g>
            <path d="M350 55h40v40M70 405H30v-40" fill="none" stroke="#f1efed" stroke-width="4"/>
            <text x="330" y="425" fill="#f1efed" font-size="48" font-weight="900" font-family="Arial">01</text>
        </svg>
        <div class="art-caption"><span>STRENGTH IS A HABIT.</span><span>MAKE IT YOURS. ↗</span></div>
    </div>
</section>
<div class="ticker" aria-hidden="true"><span>STRENGTH</span><b>✳</b><span>CONSISTENCY</span><b>✳</b><span>COMMUNITY</span><b>✳</b><span>LOOKSMAXXING</span></div>
<section class="section" id="plans">
    <div class="section-heading"><div><p class="eyebrow">01 / THE MEMBERSHIPS</p><h2>YOUR GOALS.<br>YOUR GAME PLAN.</h2></div><p>Four ways to start. One stronger you.<br>Every membership runs for 30 days.</p></div>
    <div class="plan-grid">
    @forelse($levels as $level)
        <article class="plan-card {{ $level->name === 'Gold' ? 'plan-featured' : '' }}">
            <div class="plan-top"><span class="plan-index">0{{ $loop->iteration }}</span>@if($level->name === 'Gold')<span class="pill">THE ALL-ROUNDER</span>@endif</div>
            <h3>{{ $level->name }}</h3>
            <p class="price"><span>₱</span>{{ number_format($level->fee_cents / 100, 0) }}<small>/ {{ $level->validity_days }} days</small></p>
            <ul>@foreach($level->benefits as $benefit)<li><span aria-hidden="true">↗</span>{{ $benefit }}</li>@endforeach</ul>
            <a class="plan-footer" href="{{ route('membership.create', ['level' => $level->id]) }}" aria-label="Apply for {{ $level->name }} membership">CHOOSE {{ strtoupper($level->name) }} <span aria-hidden="true">↗</span></a>
        </article>
    @empty
        <p>Membership plans are currently unavailable. Please check back soon.</p>
    @endforelse
    </div>
    <div class="discount-bar"><strong>THERE'S A PLACE FOR YOU.</strong><div>@foreach($categories as $category)<span>{{ $category->name }} <b>{{ $category->discount_percent }}% off</b></span>@endforeach</div><small>One category discount applies to your membership fee. Eligibility is checked by staff.</small></div>
</section>
<section class="location-section" id="location">
    <div><p class="eyebrow">02 / YOUR NEIGHBORHOOD. YOUR GYM.</p><h2>BUILT FOR<br><span>TONDO.</span></h2><p>Your routine starts close to home.<br>LooksMaxxing Fitness Academy · Tondo, Manila</p></div>
    @include('membership.location', ['editable' => false])
</section>
@endsection
