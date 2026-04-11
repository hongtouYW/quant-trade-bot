<nav class="nav-fixed-container">
    <div class="navbar navbar-light">
        <div class="navbar-left">
            <div class="logo-area">
                <a class="navbar-brand" href="{{route('dashboard')}}">
                    <h1 style='margin:0'>明顺</h1>
                </a>
                <a href="#" class="sidebar-toggle">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="svg replaced-svg">
                        <path fill="#525768"
                            d="M5,8H19a1,1,0,0,0,0-2H5A1,1,0,0,0,5,8Zm16,3H3a1,1,0,0,0,0,2H21a1,1,0,0,0,0-2Zm-2,5H5a1,1,0,0,0,0,2H19a1,1,0,0,0,0-2Z">
                        </path>
                    </svg>
                </a>
            </div>
            <ul class="navbar-right__menu">
                <li class="nav-author">
                    <div class="dropdown-custom">
                        <a href="javascript:;" class="nav-item-toggle">
                            <img src="{{ asset('picture/author-nav.png') }}" alt="" class="rounded-circle">
                            <span class="nav-item__title">
                                {{ Auth::user()->username }}
                                <i class="las la-angle-down nav-item__arrow">
                                </i>
                            </span>
                        </a>
                        <div class="dropdown-wrapper">
                            <div class="nav-author__info">
                                <div class="author-img">
                                    <img src="{{ asset('picture/author-nav.png') }}" alt=""
                                        class="rounded-circle">
                                </div>
                                <div>
                                    <h6 class="text-capitalize">
                                        {{ Auth::user()->username }}
                                    </h6>
                                    <span>
                                        {{ Auth::user()->role->pluck('name')->implode(', ') }}
                                    </span>
                                </div>
                            </div>
                            <div class="nav-author__options">
                                <ul>
                                    <li>
                                        <a href="{{ route('users.edit', Auth::user()->id) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                class="svg replaced-svg">
                                                <path
                                                    d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4">
                                                </path>
                                            </svg>
                                            更改用户详情
                                        </a>
                                        <a href="{{ route('users.show', Auth::user()->id) }}">
                                            <i class='bx bx-user'></i>
                                            显示用户详情
                                        </a>
                                    </li>
                                </ul>
                                <a class="nav-author__signout" href='{{ route('logout') }}'>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" class="svg replaced-svg">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4">
                                        </path>
                                        <polyline points="16 17 21 12 16 7">
                                        </polyline>
                                        <line x1="21" y1="12" x2="9" y2="12">
                                        </line>
                                    </svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>
