@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\App;
@endphp

<nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl my-3 ms-3" id="navbarBlur">
    <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                <li class="breadcrumb-item text-sm"><a class="opacity-5" href="{{ route('dashboard') }}">{{__('messages.home')}}</a></li>
                <li class="breadcrumb-item text-sm active" aria-current="page">@yield('title')</li>
            </ol>
        </nav>
        <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4 flex-grow-1" id="navbar">
            <ul class="navbar-nav justify-content-end w-full">
                <li class="nav-item d-flex align-items-center me-3">
                    <span class="nav-link text-body font-weight-bold px-0">
                        <i class="fa fa-user me-sm-1"></i>
                        <span class="d-sm-inline d-none">{{ Auth::user()->user_name ?? 'Guest' }}</span>
                    </span>
                </li>

                {{-- Language Switcher Dropdown --}}
                <li class="nav-item dropdown d-flex align-items-center me-3">
                    {{-- REMOVED data-bs-toggle="dropdown" --}}
                    <a href="#" class="nav-link text-body p-0" id="languageDropdown" role="button" aria-expanded="false">
                        <i class="fa fa-globe me-1"></i>
                        <span class="d-sm-inline d-none">{{ strtoupper(App::getLocale()) }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4" aria-labelledby="languageDropdown">
                        <li>
                            <a class="dropdown-item border-radius-md {{ App::getLocale() === 'en' ? 'active bg-gradient-primary text-white' : '' }}" href="{{ route('lang.set', ['locale' => 'en']) }}">
                                English
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item border-radius-md {{ App::getLocale() === 'zh' ? 'active bg-gradient-primary text-white' : '' }}" href="{{ route('lang.set', ['locale' => 'zh']) }}">
                                中文
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- Theme Switcher Toggle --}}
                <li class="nav-item d-flex align-items-center">
                    <a href="#" class="nav-link text-body p-0" id="themeToggle" title="{{ __('messages.theme') }}">
                        <i class="fa fa-moon theme-icon"></i>
                        <span class="d-sm-inline d-none ms-1">{{ __('messages.theme') }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
@push('scripts')
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('.theme-icon');
        const htmlElement = document.documentElement;
        const savedTheme = localStorage.getItem('theme') || '{{ Auth::check() ? Auth::user()->theme ?? 'light' : 'light' }}';

        htmlElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', (e) => {
            e.preventDefault();
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            @if (Auth::check())
                fetch('{{ route('theme.set') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ theme: newTheme })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('[Theme] Failed to save theme preference');
                    }
                })
                .catch(error => console.error('[Theme] Fetch error:', error)); 
            @endif
        });

        function updateThemeIcon(theme) {
            themeIcon.classList.remove('fa-moon', 'fa-sun');
            themeIcon.classList.add(theme === 'light' ? 'fa-moon' : 'fa-sun');
        }
        window.onload = function() {
            if (typeof bootstrap === 'undefined' || typeof bootstrap.Dropdown === 'undefined') {
                console.error('[Dropdown Init] Bootstrap JS (or Dropdown component) is not loaded or available!');
                return;
            }
            const languageDropdownElement = document.getElementById('languageDropdown');
            if (languageDropdownElement) {
                try {
                    const languageDropdown = new bootstrap.Dropdown(languageDropdownElement);
                    languageDropdownElement.addEventListener('click', function(event) {
                        event.preventDefault();
                        languageDropdown.toggle();
                    });
                } catch (error) {
                    console.error('[Dropdown Init] Error initializing Bootstrap Dropdown:', error);
                }
            } else {
                console.error('[Dropdown Init] Language dropdown element with ID "languageDropdown" not found!');
            }
        };
    </script>
@endpush