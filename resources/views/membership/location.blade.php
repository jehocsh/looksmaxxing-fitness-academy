<div class="location-widget" data-location-widget data-weather-url="{{ route('weather.show') }}" data-lat="{{ $latitude ?? config('gym.latitude') }}" data-lon="{{ $longitude ?? config('gym.longitude') }}" data-editable="{{ $editable ? '1' : '0' }}">
    <div class="map-canvas" data-map-canvas><div class="map-placeholder"><strong>Tondo, Manila</strong><span>Location map is currently unavailable.</span>@if($editable)<small>You can still submit your application.</small>@endif</div></div>
    <div class="weather-panel" data-weather-panel aria-live="polite">Loading current weather…</div>
    <a class="text-link map-link" href="https://www.google.com/maps/search/?api=1&amp;query=14.6189095%2C120.9660386" target="_blank" rel="noopener noreferrer">View Tondo on Google Maps ↗</a>
    @if($editable)
    <div class="form-grid coordinate-fields"><div class="field"><label for="latitude">Latitude</label><input type="number" step="any" min="-90" max="90" name="latitude" id="latitude" value="{{ $latitude }}" placeholder="Optional"></div><div class="field"><label for="longitude">Longitude</label><input type="number" step="any" min="-180" max="180" name="longitude" id="longitude" value="{{ $longitude }}" placeholder="Optional"></div></div>
    <div class="coordinate-actions"><p class="muted" data-coordinate-label>Choose an area on the map or leave the coordinates blank.</p><button class="text-button" type="button" data-clear-location>Clear location</button></div>
    @else<p class="muted map-caption">Area view: Tondo, Manila. A street address has not been specified.</p>@endif
</div>
@once
    @push('scripts')
        @if(config('services.google_maps.key'))
        <script async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google_maps.key')) }}&amp;loading=async&amp;callback=initLooksMaxxingMap"></script>
        @endif
    @endpush
@endonce
