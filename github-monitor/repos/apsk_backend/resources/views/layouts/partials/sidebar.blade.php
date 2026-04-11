@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\App;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;
    use App\Models\Module;
@endphp
<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3 bg-white" id="sidenav-main">
    <div class="sidenav-header">
        <a class="navbar-brand m-0" href="{{ route('dashboard') }}">
            <img src="{{ asset('assets/img/logo-ct.png') }}" class="navbar-brand-img h-100" alt="main_logo">
            <span class="ms-1 font-weight-bold">{{ __('messages.title') }}</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0">
    <div id="sidenav-collapse-main">
        <ul class="navbar-nav sidebar-nav" id="sidebar-nav">
            <li class="nav-item">
                <a class="nav-link {{ Route::is('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-tv-2 text-primary text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">{{ __('messages.dashboard') }}</span>
                </a>
            </li>
            {{-- Dynamic Module List --}}
                @if (Auth::check())
                    @php
                        $tbl_section = GetSectionList();
                    @endphp
                    @foreach ($tbl_section as $section)
                        <li class="nav-item">
                            <a class="nav-link collapsed" data-bs-target="#{{$section->section}}-nav" 
                                data-bs-toggle="collapse" href="#" aria-expanded="false">
                                <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 
                                    d-flex align-items-center justify-content-center">
                                    <i class="ni ni-building text-info text-sm opacity-10"></i>
                                </div>
                                <span>{{ __('section.' . $section->section) }}</span>
                                <i class="bi bi-chevron-down ms-auto"></i>
                            </a>
                            <ul id="{{$section->section}}-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                                @foreach ($allowedModules as $module)
                                    @if ( $module && Route::has('admin.'.$module->controller.'.index') )
                                        @if ( $module->section === $section->section )
                                            <li class="nav-item">
                                                <a class="nav-link {{ Route::is('admin.'.$module->controller.'.index') ? 'active' : '' }}" 
                                                    href="{{ route('admin.'.$module->controller.'.index') }}">
                                                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 
                                                        d-flex align-items-center justify-content-center">
                                                        <i class="ni ni-building text-info text-sm opacity-10"></i>
                                                    </div>
                                                    <span class="nav-link-text ms-1">{{ __('module.' . $module->module_name) }}</span>
                                                </a>
                                            </li>
                                        @endif
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                @endif
            {{-- End Dynamic Module List --}}

            {{-- Profile Setting --}}
            <li class="nav-item">
                <a class="nav-link {{ Route::is('setup') ? 'active' : '' }}" href="{{ route('setup') }}">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-tv-2 text-primary text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">{{ __('messages.setup') }}</span>
                </a>
            </li>
            {{-- Profile Setting List --}}
            <li class="nav-item">
                <a class="nav-link" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-user-run text-danger text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">{{ __('messages.logout') }}</span>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </li>
        </ul>
    </div>
</aside>
