@php
    use App\Config\MenuConfig;
    $menuItems = MenuConfig::getMenuItems();
@endphp

<link href="{{ asset('assets/css/app/sidebar.css') }}" rel="stylesheet" type="text/css" />

<div class="container1">
    <div class="sidebar-wrapper sidebar-theme cf-sidebar-shell">
        <nav id="compactSidebar" class="sliderbar">
            <ul class="menu-categories sliderbar">
            @foreach($menuItems as $menu)
                <li class="menu">
                    <a href="#{{ $menu['id'] }}" data-active="false" class="menu-toggle">
                        <div class="base-menu">
                            <div class="base-icons">
                                {!! MenuConfig::renderIcon($menu['icon']) !!}
                            </div>
                            <span class="sidebar-module-name-small">{{ $menu['title'] }}</span>
                        </div>
                    </a>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="feather feather-chevron-left">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </li>
            @endforeach
            </ul>
        </nav>

        <div id="compact_submenuSidebar" class="submenu-sidebar ps">
            @foreach($menuItems as $menu)
                <div class="submenu {{ $loop->first ? 'show' : '' }}" id="{{ $menu['id'] }}">
                    <ul class="submenu-list" data-parent-element="#{{ $menu['id'] }}">
                        @foreach($menu['submenu'] as $submenu)
                            <li>
                                <a href="{{ url($submenu['url']) }}" class="menu-toggle" data-active="false">
                                    <div class="base-menu">
                                        <div class="base-icons">
                                            {!! MenuConfig::renderSubmenuIcon($submenu['icon']) !!}
                                        </div>
                                        <span>{{ $submenu['title'] }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</div>
