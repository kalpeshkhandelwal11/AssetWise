<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'AssetWise'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950 font-sans antialiased" x-data="{ sidebarOpen: false, sidebarCollapsed: false }">

{{-- Mobile overlay --}}
<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" @click="sidebarOpen = false"></div>

{{-- ======== SIDEBAR ======== --}}
<aside
    :class="sidebarCollapsed ? 'w-16' : 'w-64'"
    class="fixed inset-y-0 left-0 z-40 flex flex-col bg-gray-900 dark:bg-gray-950 transition-all duration-300 ease-in-out
           -translate-x-full lg:translate-x-0"
    :style="sidebarOpen ? 'transform:translateX(0)' : ''">

    {{-- Logo --}}
    <div class="flex h-16 items-center justify-between px-4 border-b border-gray-700/50 flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 overflow-hidden">
            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                </svg>
            </div>
            <span x-show="!sidebarCollapsed" class="text-white font-bold text-lg tracking-tight transition-opacity duration-200">AssetWise</span>
        </a>
        <button @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex text-gray-400 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 12l-7-7 7-7"/>
            </svg>
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-2 py-4 space-y-1 scrollbar-thin scrollbar-thumb-gray-700">
        @include('layouts.sidebar-nav')
    </nav>

    {{-- Bottom: user mini + version --}}
    <div class="flex-shrink-0 border-t border-gray-700/50 p-3">
        <div class="flex items-center gap-2 overflow-hidden">
            <div class="w-8 h-8 rounded-full bg-indigo-600 flex-shrink-0 flex items-center justify-center">
                <span class="text-white text-xs font-semibold">{{ substr(auth()->user()->name ?? 'U', 0, 1) }}</span>
            </div>
            <div x-show="!sidebarCollapsed" class="min-w-0">
                <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name ?? '' }}</p>
                <p class="text-gray-400 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
            </div>
        </div>
    </div>
</aside>

{{-- ======== MAIN WRAPPER ======== --}}
<div :class="sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64'" class="flex flex-col min-h-screen transition-all duration-300">

    {{-- ======== TOPBAR ======== --}}
    <header class="sticky top-0 z-20 flex h-16 items-center gap-4 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 sm:px-6 shadow-sm">

        {{-- Mobile menu toggle --}}
        <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- Breadcrumb / page title --}}
        <div class="flex-1 min-w-0">
            @hasSection('breadcrumb')
                <div class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                    @yield('breadcrumb')
                </div>
            @else
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">@yield('page-title', 'Dashboard')</h1>
            @endif
        </div>

        {{-- Right actions --}}
        <div class="flex items-center gap-3">

            {{-- Global search --}}
            <div class="hidden sm:block relative" x-data="{ open: false }">
                <input @focus="open = true" @click.outside="open = false" type="text" placeholder="Search assets…" class="w-56 pl-9 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <svg class="absolute left-2.5 top-2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            {{-- Notification bell --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    {{-- Unread badge --}}
                    @php $unread = auth()->user() ? (method_exists(auth()->user(), 'unreadNotifications') ? auth()->user()->unreadNotifications->count() : 0) : 0; @endphp
                    @if($unread > 0)
                        <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </button>

                <div x-show="open" x-transition @click.outside="open = false"
                     class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Notifications</span>
                        <a href="#" class="text-xs text-indigo-600 hover:text-indigo-700">Mark all read</a>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <p class="px-4 py-8 text-center text-sm text-gray-400">No new notifications</p>
                    </div>
                    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                        <a href="#" class="block text-center text-xs text-indigo-600 hover:text-indigo-700 py-1">View all notifications</a>
                    </div>
                </div>
            </div>

            {{-- User dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <div class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-semibold">{{ substr(auth()->user()->name ?? 'U', 0, 1) }}</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-transition @click.outside="open = false"
                     class="absolute right-0 mt-2 w-52 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ auth()->user()->name ?? '' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                    </div>
                    <div class="py-1">
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- ======== FLASH MESSAGES ======== --}}
    @if(session()->hasAny(['success', 'error', 'warning', 'info']))
        <div class="px-4 sm:px-6 pt-4 space-y-2" x-data x-init="setTimeout(() => $el.remove(), 5000)">
            @foreach(['success' => 'green', 'error' => 'red', 'warning' => 'amber', 'info' => 'blue'] as $type => $color)
                @if(session($type))
                    <div x-data="{ show: true }" x-show="show" x-transition class="flex items-center gap-3 px-4 py-3 rounded-lg bg-{{ $color }}-50 dark:bg-{{ $color }}-900/20 border border-{{ $color }}-200 dark:border-{{ $color }}-800 text-{{ $color }}-800 dark:text-{{ $color }}-300 text-sm">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            @if($type === 'success') <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            @elseif($type === 'error') <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            @else <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            @endif
                        </svg>
                        <span class="flex-1">{{ session($type) }}</span>
                        <button @click="show = false" class="text-{{ $color }}-500 hover:text-{{ $color }}-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ======== PAGE CONTENT ======== --}}
    <main class="flex-1 px-4 sm:px-6 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="px-6 py-3 border-t border-gray-200 dark:border-gray-800">
        <p class="text-xs text-gray-400">AssetWise &copy; {{ date('Y') }} &mdash; Enterprise Asset Management</p>
    </footer>
</div>

@stack('scripts')
</body>
</html>
