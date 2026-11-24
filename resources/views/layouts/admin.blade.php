@extends('layouts.site')
@section('content')
<div class="admin-shell">
    <aside class="admin-nav" aria-label="Administrator navigation">
        <p class="eyebrow">ACADEMY CONTROL</p>
        @foreach(['dashboard' => ['Overview', '01'], 'members.index' => ['Members', '02'], 'transactions.index' => ['Transactions', '03'], 'categories.index' => ['Categories', '04'], 'levels.index' => ['Membership levels', '05']] as $routeName => [$label, $number])
            <a class="{{ request()->routeIs($routeName) || ($routeName === 'members.index' && request()->routeIs('members.*')) || ($routeName === 'transactions.index' && request()->routeIs('transactions.*')) ? 'selected' : '' }}" href="{{ route($routeName) }}"><span>{{ $number }}</span>{{ $label }}<b aria-hidden="true">↗</b></a>
        @endforeach
        <a class="{{ request()->routeIs('email-status') ? 'selected' : '' }}" href="{{ route('email-status') }}"><span>06</span>Email status<b aria-hidden="true">↗</b></a>
        <a href="{{ route('membership.create') }}"><span>↗</span>Application form</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="nav-logout" type="submit">Sign out ↗</button></form>
    </aside>
    <section class="admin-content">
        @if(session('success'))<div class="success-box" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="error-box" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('admin-content')
    </section>
</div>
@endsection
