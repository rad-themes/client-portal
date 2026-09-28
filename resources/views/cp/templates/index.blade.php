@extends('statamic::layout')

@section('title', __('Portal Templates'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ __('Portal Starter Templates') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Pre-configured phases and modules for instant client onboarding.') }}</p>
        </div>
        <a href="{{ route('statamic.cp.client-portal.index') }}" class="btn-flat">
            ← {{ __('Back to Portals') }}
        </a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        @foreach($templates as $key => $template)
            <div class="card p-6 flex flex-col justify-between space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ $template['title'] }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $template['description'] }}</p>

                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">{{ __('Phases Included') }}:</h4>
                        <ul class="space-y-1 text-sm text-gray-700">
                            @foreach($template['phases'] as $phase)
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>{{ $phase['title'] }}</span>
                                    <span class="text-xs text-gray-400">({{ count($phase['modules'] ?? []) }} modules)</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <a href="{{ route('statamic.cp.client-portal.create', ['template' => $key]) }}" class="btn-primary w-full text-center">
                        {{ __('Use This Template') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endsection
