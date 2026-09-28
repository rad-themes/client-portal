<?php

namespace Komalnakrani\ClientPortal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Komalnakrani\ClientPortal\Activity;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\User;
use Statamic\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    /**
     * Files clients may upload. Anything executable or renderable as a page is excluded.
     */
    private const UPLOAD_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,txt,csv,rtf,zip,jpg,jpeg,png,gif,webp,heic,mp3,mp4,mov,ai,psd,eps,sketch,fig';

    public function index(): View|RedirectResponse
    {
        $portals = Portals::forUser(User::current());

        if ($portals->count() === 1) {
            return redirect()->route('client-portal.show', $portals->first()->slug());
        }

        return $this->view('index', ['title' => __('Your portals'), 'portals' => $portals]);
    }

    public function show(string $portal): View
    {
        $entry = $this->authorizedPortal($portal);

        return $this->view('show', ['progress' => Portals::progress($entry)])->cascadeContent($entry);
    }

    public function page(string $portal, string $module): View
    {
        $entry = $this->authorizedPortal($portal);
        $this->usableModule($entry, $module, 'content');

        return $this->view('page', ['module_id' => $module])->cascadeContent($entry);
    }

    public function download(string $portal, string $module, int $index): StreamedResponse
    {
        $entry = $this->authorizedPortal($portal);
        $found = $this->usableModule($entry, $module, ['file', 'upload']);

        $field = $found['type'] === 'file' ? 'files' : 'uploads';
        $path = ((array) ($found[$field] ?? []))[$index] ?? abort(404);
        $container = AssetContainer::find(Portals::FILES_CONTAINER) ?? abort(404);

        abort_unless($container->disk()->exists($path), 404);

        return $container->disk()->filesystem()->download($path);
    }

    public function complete(string $portal, string $module): RedirectResponse
    {
        $entry = $this->authorizedPortal($portal);
        $found = $this->usableModule($entry, $module);

        abort_unless($found['client_can_complete'] ?? false, 403);

        if (Portals::status($found) !== 'complete') {
            Portals::updateModule($entry, $module, fn (array $item) => array_merge($item, [
                'status' => 'complete',
                'completed_at' => now()->toIso8601String(),
                'completed_by' => User::current()->id(),
            ]));

            Activity::record($entry, User::current(), __('completed “:module”', ['module' => $found['title'] ?? '']));
        }

        return redirect()->route('client-portal.show', $entry->slug())->with('portal_status', __('Marked as complete. Thank you!'));
    }

    public function upload(Request $request, string $portal, string $module): RedirectResponse
    {
        $entry = $this->authorizedPortal($portal);
        $found = $this->usableModule($entry, $module, 'upload');

        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.((int) Portals::setting('max_upload_mb', 20) * 1024), 'extensions:'.self::UPLOAD_EXTENSIONS],
        ]);

        $container = AssetContainer::find(Portals::FILES_CONTAINER) ?? abort(500, 'Run php please client-portal:install');
        $paths = [];

        foreach ($request->file('files') as $file) {
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
            $path = "uploads/{$entry->slug()}/".now()->format('Ymd-His').'-'.Str::random(6)."-{$name}.".strtolower($file->getClientOriginalExtension());

            $container->disk()->filesystem()->putFileAs(dirname($path), $file, basename($path));
            Asset::make()->container($container->handle())->path($path)->save();
            $paths[] = $path;
        }

        Portals::updateModule($entry, $module, fn (array $item) => array_merge($item, [
            'uploads' => array_values(array_merge((array) ($item['uploads'] ?? []), $paths)),
        ]));

        Activity::record($entry, User::current(), trans_choice('uploaded :count file to “:module”|uploaded :count files to “:module”', count($paths), ['module' => $found['title'] ?? '']));

        return redirect()->route('client-portal.show', $entry->slug())->with('portal_status', __('Thanks! Your files were uploaded.'));
    }

    private function authorizedPortal(string $slug): Entry
    {
        $entry = Portals::findBySlug($slug) ?? abort(404);

        Gate::authorize('view-client-portal', $entry);

        return $entry;
    }

    /**
     * The module, if it exists, is not inactive and is one of the given types.
     *
     * @param  string|array<int, string>|null  $types
     * @return array<string, mixed>
     */
    private function usableModule(Entry $portal, string $moduleId, string|array|null $types = null): array
    {
        $module = Portals::findModule($portal, $moduleId)['module'] ?? abort(404);

        abort_if(Portals::status($module) === 'inactive', 404);
        abort_if($types && ! in_array($module['type'] ?? null, (array) $types, true), 404);

        return $module;
    }
}
