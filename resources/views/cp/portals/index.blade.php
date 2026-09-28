@extends('statamic::layout')

@section('title', __('Client Portals'))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ __('Client Portals') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Manage your client project portals, phases, deliverables, and security settings.') }}</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('statamic.cp.client-portal.templates.index') }}" class="btn-flat">
                {{ __('Templates') }}
            </a>
            <a href="{{ route('statamic.cp.client-portal.create') }}" class="btn-primary">
                {{ __('Create Portal') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-md bg-green-50 p-4 border border-green-200">
            <div class="flex">
                <div class="flex-shrink-0 text-green-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                </div>
                <div class="ml-3 font-medium text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            </div>
        </div>
    @endif

    <div class="card p-0 overflow-hidden">
        @if($portals->isEmpty())
            <div class="p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900">{{ __('No Client Portals Found') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Get started by creating a new client portal or selecting a pre-configured template.') }}</p>
                <div class="mt-6">
                    <a href="{{ route('statamic.cp.client-portal.create') }}" class="btn-primary">
                        {{ __('Create Your First Portal') }}
                    </a>
                </div>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Access Mode') }}</th>
                        <th>{{ __('Progress') }}</th>
                        <th class="actions-column"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($portals as $portal)
                        <tr>
                            <td class="font-semibold">
                                <a href="{{ $portal['cp_edit_url'] }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $portal['title'] }}
                                </a>
                                <span class="block text-xs text-gray-400 font-normal">/portal/{{ $portal['slug'] }}</span>
                            </td>
                            <td>
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                    {{ $portal['project_status'] }}
                                </span>
                            </td>
                            <td>
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                                    {{ ucfirst(str_replace('_', ' ', $portal['access_type'])) }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-600 rounded-full" style="width: {{ $portal['progress'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-600">{{ $portal['progress'] }}%</span>
                                </div>
                            </td>
                            <td class="actions-column flex items-center justify-end space-x-2">
                                <a href="{{ $portal['public_url'] }}" target="_blank" class="btn-sm" title="{{ __('View Frontend Portal') }}">
                                    {{ __('View') }} ↗
                                </a>
                                <a href="{{ $portal['cp_edit_url'] }}" class="btn-sm" title="{{ __('Edit Portal') }}">
                                    {{ __('Edit') }}
                                </a>
                                <form action="{{ route('statamic.cp.client-portal.duplicate', $portal['id']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-sm" title="{{ __('Duplicate Portal') }}">
                                        {{ __('Duplicate') }}
                                    </button>
                                </form>
                                <form action="{{ route('statamic.cp.client-portal.destroy', $portal['id']) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this portal?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-sm text-red-600 hover:text-red-800" title="{{ __('Delete Portal') }}">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
