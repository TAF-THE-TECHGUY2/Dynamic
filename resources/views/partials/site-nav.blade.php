@php($phone = settings('business.phone', '061 687 4334'))

<div class="ds-nav" x-data="siteNav">
    <a href="{{ route('home') }}" class="ds-logo" aria-label="DS Dynamic Solutions home">
        <div class="ds-mark"><span class="d">D</span><span>S</span></div>
        <div class="ds-slash"></div>
        <div>
            <div class="w1">DYNAMIC</div>
            <div class="w2">SOLUTIONS</div>
        </div>
    </a>

    <button type="button" class="ds-navtoggle" x-on:click="toggle()"
            :aria-expanded="open ? 'true' : 'false'" aria-controls="site-menu">
        <span class="sr-only">Menu</span>
        <svg width="18" height="14" viewBox="0 0 18 14" fill="none" aria-hidden="true">
            <path d="M0 1h18M0 7h18M0 13h18" stroke="currentColor" stroke-width="2"/>
        </svg>
    </button>

    <nav id="site-menu" :class="open && 'open'">
        <a href="{{ route('home') }}" @class(['act' => request()->routeIs('home')])>Home</a>
        <a href="{{ route('bulk-diesel') }}" @class(['act' => request()->routeIs('bulk-diesel')])>Bulk diesel</a>
        <a href="{{ route('truck-stop') }}" @class(['act' => request()->routeIs('truck-stop')])>Truck stop</a>
        <a href="{{ route('quote') }}" @class(['act' => request()->routeIs('quote')])>Get a quote</a>
        <a href="{{ route('contact') }}" @class(['act' => request()->routeIs('contact')])>Contact</a>
    </nav>

    <a class="ds-callbtn" href="tel:{{ preg_replace('/\s+/', '', $phone) }}">
        <div>
            <div class="l">CALL NOW</div>
            <div class="n">{{ $phone }}</div>
        </div>
    </a>
</div>
