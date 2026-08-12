{{--
    The pump counter.

    The final value is rendered here, in the server HTML, so it is visible with
    JavaScript disabled and indexable by a crawler. Alpine animates up to it;
    it never renders zeros and fills them in.

    The timestamp and "subject to change" line are not optional decoration — a
    screenshot of a stale price has to be self-evidently stale.

    @param array $prices          plain rows from PriceBook::display()
    @param \Illuminate\Support\Carbon|null $lastChangedAt
--}}
<div class="ds-pump">
    <div class="rule"></div>

    @forelse ($prices as $index => $row)
        <div @class(['second' => $index > 0])>
            <div class="grade">
                <div class="gname">{{ Str::upper($row['label']) }}</div>
                <div class="gsub">{{ Str::upper($row['blurb'] ?? '') }}</div>
            </div>
            <div class="digits">
                <span x-data="pumpCounter({{ (float) $row['price_per_litre'] }})"
                      x-text="display">R{{ number_format((float) $row['price_per_litre'], 2) }}</span><small>/ L</small>
            </div>
        </div>
    @empty
        <div>
            <div class="grade">
                <div class="gname">DIESEL</div>
                <div class="gsub">PRICE ON REQUEST</div>
            </div>
            <div class="digits">R—.—<small>/ L</small></div>
            <p style="color:#8A9096;font-size:12px;line-height:1.62;margin:10px 0 0">
                Today's price has not been published yet. Call
                <a href="tel:{{ preg_replace('/\s+/', '', settings('business.phone', '')) }}"
                   style="color:#fff">{{ settings('business.phone') }}</a>
                for a rate.
            </p>
        </div>
    @endforelse

    <div class="stamp">
        @if ($lastChangedAt)
            UPDATED {{ Str::upper($lastChangedAt->timezone(config('app.timezone'))->format('j M Y')) }} ·
        @endif
        INCL. VAT · SUBJECT TO CHANGE
    </div>
</div>
