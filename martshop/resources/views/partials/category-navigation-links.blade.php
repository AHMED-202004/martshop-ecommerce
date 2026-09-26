@foreach($categories as $category)
  <li>
    <a href="{{ url('/c/'.$category['slug']) }}">
      <i class="{{ $category['icon'] }}"></i>
      <span>{{ $category['name'] }}</span>
    </a>
  </li>
@endforeach
