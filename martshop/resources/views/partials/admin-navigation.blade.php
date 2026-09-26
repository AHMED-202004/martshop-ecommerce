<details class="admin-menu">
  <summary class="btn btn-primary-outline">أقسام الإدارة</summary>
  <nav class="admin-menu-links" aria-label="أقسام الإدارة">
    @foreach($links as $link)
      <a href="{{ route($link['route']) }}" @if(request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['title'] }}</a>
    @endforeach
  </nav>
</details>
