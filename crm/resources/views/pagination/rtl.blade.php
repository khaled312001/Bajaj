@if ($paginator->hasPages())
    <div class="pager" role="navigation">
        @if ($paginator->onFirstPage())<span class="dis"><i class="fa-solid fa-chevron-right"></i></span>
        @else<a href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="fa-solid fa-chevron-right"></i></a>@endif

        @foreach ($elements as $element)
            @if (is_string($element))<span class="dis">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span class="cur">{{ $page }}</span>
                    @else<a href="{{ $url }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next"><i class="fa-solid fa-chevron-left"></i></a>
        @else<span class="dis"><i class="fa-solid fa-chevron-left"></i></span>@endif
    </div>
    <div class="pager-info">عرض {{ $paginator->firstItem() }} – {{ $paginator->lastItem() }} من أصل {{ $paginator->total() }}</div>
@endif
