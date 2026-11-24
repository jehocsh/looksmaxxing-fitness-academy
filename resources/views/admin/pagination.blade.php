@if($paginator->hasPages())
<div class="pagination-controls" role="navigation" aria-label="Pagination">
    @if($paginator->onFirstPage())<span class="muted">← Previous</span>@else<a class="text-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Previous</a>@endif
    <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="text-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next →</a>@else<span class="muted">Next →</span>@endif
</div>
@endif
