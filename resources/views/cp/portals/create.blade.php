@extends('statamic::layout')

@section('title', __('Create Client Portal'))

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ __('Create Client Portal') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ __('Set up a new client portal or choose a pre-configured template.') }}</p>
            </div>
            <a href="{{ route('statamic.cp.client-portal.index') }}" class="btn-flat">
                ← {{ __('Back to Portals') }}
            </a>
        </div>

        <form action="{{ route('statamic.cp.client-portal.store') }}" method="POST" class="card p-6 space-y-6">
            @csrf

            <div>
                <label for="title" class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Portal Title') }} <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="title" required placeholder="{{ __('e.g. Acme Corp Website Redesign') }}"
                    class="input-text w-full @error('title') border-red-500 @enderror" value="{{ old('title') }}">
                @error('title')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-semibold text-gray-900 mb-1">{{ __('URL Slug') }} <span class="text-red-500">*</span></label>
                <input type="text" name="slug" id="slug" required placeholder="{{ __('e.g. acme-website-redesign') }}"
                    class="input-text w-full @error('slug') border-red-500 @enderror" value="{{ old('slug') }}">
                <p class="text-xs text-gray-500 mt-1">{{ __('Accessible at /portal/your-slug') }}</p>
            </div>

            <div>
                <label for="template" class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Starter Template (Optional)') }}</label>
                <select name="template" id="template" class="input-text w-full">
                    <option value="">{{ __('None (Blank Portal)') }}</option>
                    @foreach($templates as $key => $template)
                        <option value="{{ $key }}">{{ $template['title'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">{{ __('Selecting a template will populate phases, modules, and deliverables automatically.') }}</p>
            </div>

            <div>
                <label for="access_type" class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Access Mode') }} <span class="text-red-500">*</span></label>
                <select name="access_type" id="access_type" class="input-text w-full" onchange="togglePasswordInput(this.value)">
                    <option value="user_login">{{ __('Logged-in Client Users Only') }}</option>
                    <option value="password">{{ __('Password Protected') }}</option>
                    <option value="token">{{ __('Direct Link Access Key') }}</option>
                    <option value="public">{{ __('Public Access') }}</option>
                </select>
            </div>

            <div id="password-container" class="hidden">
                <label for="access_password" class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Portal Password') }}</label>
                <input type="text" name="access_password" id="access_password" placeholder="{{ __('Set password for portal') }}" class="input-text w-full">
            </div>

            <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-4">
                <a href="{{ route('statamic.cp.client-portal.index') }}" class="btn-flat">{{ __('Cancel') }}</a>
                <button type="submit" class="btn-primary">{{ __('Create Portal') }}</button>
            </div>
        </form>
    </div>

    <script>
        function togglePasswordInput(val) {
            const container = document.getElementById('password-container');
            if (val === 'password') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }
    </script>
@endsection
