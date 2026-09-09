<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 font-sans antialiased text-slate-800">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex h-16 items-center justify-between">

                @php
                    $logo = config('drogueria.logo');
                    $hasLogo = $logo && is_file(public_path($logo));
                @endphp

                <a href="{{ route('pos') }}" wire:navigate class="flex items-center gap-3 shrink-0">
                    @if ($hasLogo)
                        {{-- El logo ya incluye el nombre, no hace falta repetirlo. --}}
                        <img
                            src="{{ asset($logo) }}"
                            alt="{{ config('drogueria.name') }}"
                            class="h-9 w-auto"
                        >
                    @else
                        <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/>
                            </svg>
                        </div>
                        <span class="font-extrabold tracking-tight text-slate-900">
                            {{ config('drogueria.name') }}
                        </span>
                    @endif
                </a>

                <div class="flex items-center gap-1">
                    @php
                        $links = [
                            ['route' => 'pos',       'label' => 'Punto de Venta'],
                            ['route' => 'inventory', 'label' => 'Inventario'],
                            ['route' => 'reports',   'label' => 'Reportes'],
                        ];
                    @endphp

                    @foreach ($links as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            wire:navigate
                            @class([
                                'px-4 py-2 rounded-lg text-sm font-semibold transition-colors',
                                'bg-blue-50 text-blue-700' => request()->routeIs($link['route']),
                                'text-slate-500 hover:text-slate-900 hover:bg-slate-100' => ! request()->routeIs($link['route']),
                            ])
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>

            </div>
        </div>
    </nav>

    <main>
        {{ $slot }}
    </main>

    {{-- Toast region: components signal with $this->dispatch('toast', type: ..., message: ...) --}}
    <div
        x-data="{
            toasts: [],
            push(detail) {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, ...detail });
                setTimeout(() => this.remove(id), 4000);
            },
            remove(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
            },
        }"
        x-on:toast.window="push($event.detail)"
        class="fixed bottom-6 right-6 z-[100] flex flex-col gap-2 w-full max-w-sm"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-end="opacity-0 translate-y-2"
                class="flex items-start justify-between gap-3 px-4 py-3 rounded-xl shadow-lg text-white text-sm font-medium"
                :class="toast.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
            >
                <span x-text="toast.message"></span>
                <button type="button" x-on:click="remove(toast.id)" class="text-lg leading-none opacity-70 hover:opacity-100">&times;</button>
            </div>
        </template>
    </div>

</body>
</html>
