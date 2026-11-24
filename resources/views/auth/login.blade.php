@extends('layouts.site')
@section('title', 'Staff sign in · LooksMaxxing')
@section('content')
<section class="login-section">
    <div class="login-intro"><p class="eyebrow">THE WORK BEHIND THE WORK.</p><h1>RUN THE<br><span>ACADEMY.</span></h1><p>One place for the people, plans, and progress that keep LooksMaxxing moving.</p><span class="outline-word" aria-hidden="true">LMX.</span></div>
    <div class="login-card"><span class="eyebrow">STAFF ACCESS / 01</span><h2>Welcome back.</h2><p>Sign in to manage the academy.</p>
        @if($errors->any())<div class="error-box" role="alert">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
            <label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password">
            <button class="button" type="submit">Sign in <span aria-hidden="true">↗</span></button>
        </form>
        <p class="form-note">Administrator accounts are provided by the academy.</p>
    </div>
</section>
@endsection
