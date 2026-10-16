@php
    $__menuSections = config('sidebar_menu', []);
    $__unreadCount = auth()->user()->unreadNotifications()->count();
    $__unreadMessageCount = \App\Models\Message::unreadCountFor(auth()->user());
    $__pendingTrackingRequestCount = auth()->user()->trackerRelations()->where('status', 'pending')->count();
    $__assignedTaskCount = auth()->user()->assignedFieldTasks()->whereIn('status', ['assigned', 'in_progress'])->count();
@endphp

<ul class="nav sidebar-menu flex-column tracker-sidebar-nav" data-lte-toggle="treeview" data-animation-speed="280" role="menu">
    @foreach ($__menuSections as $section)
        @php
            $__visibleItems = array_values(array_filter($section['items'], function ($item) {
                if (! Route::has($item['route'])) {
                    return false;
                }

                return empty($item['permission']) || auth()->user()->can($item['permission']);
            }));

            $__sectionActive = collect($__visibleItems)->contains(function ($item) {
                return request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*');
            });
        @endphp

        @continue(empty($__visibleItems))

        @if (count($__visibleItems) === 1)
            @include('layouts.partials.sidebar-menu-item', ['item' => $__visibleItems[0], 'child' => false])
        @else
            <li class="nav-item tracker-nav-parent {{ $__sectionActive ? 'menu-open' : '' }}">
                <a href="#" class="nav-link tracker-nav-parent-link {{ $__sectionActive ? 'active' : '' }}">
                    <span class="tracker-nav-icon-wrap">
                        <i class="nav-icon {{ $section['icon'] ?? 'fa-solid fa-layer-group' }}"></i>
                    </span>
                    <div class="tracker-nav-copy">
                        <p>{{ $section['label'] }}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right nav-arrow tracker-nav-arrow"></i>
                </a>
                <ul class="nav nav-treeview tracker-submenu">
                    @foreach ($__visibleItems as $item)
                        @include('layouts.partials.sidebar-menu-item', ['item' => $item, 'child' => true])
                    @endforeach
                </ul>
            </li>
        @endif
    @endforeach
</ul>
