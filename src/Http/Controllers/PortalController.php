<?php

namespace Komalnakrani\ClientPortal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Komalnakrani\ClientPortal\Activity;
use Komalnakrani\ClientPortal\Notifications\NewMessage;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\User;
use Statamic\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    /**
     * Files clients may upload. Anything executable or renderable as a page is excluded.
     */
    private const UPLOAD_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,txt,csv,rtf,zip,jpg,jpeg,png,gif,webp,heic,mp3,mp4,mov,webm,ai,psd,eps,sketch,fig';

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

        return $this->view('show', [
            'progress' => Portals::progress($entry),
            'portal_messages' => $entry->get('comments_enabled') ? Portals::messages($entry) : null,
        ])->cascadeContent($entry);
    }

    public function page(string $portal, string $module): View
    {
        $entry = $this->authorizedPortal($portal);
        $this->usableModule($entry, $module, 'content');

        return $this->view('page', ['module_id' => $module])->cascadeContent($entry);
    }

    public function download(Request $request, string $portal, string $module, int $index): StreamedResponse
    {
        $entry = $this->authorizedPortal($portal);
        $found = $this->usableModule($entry, $module, 'file');

        return $this->serve($request, ((array) ($found['files'] ?? []))[$index] ?? abort(404));
    }

    public function downloadUpload(Request $request, string $portal, string $module, string $key): StreamedResponse
    {
        $entry = $this->authorizedPortal($portal);
        $this->usableModule($entry, $module, 'upload');

        return $this->serve($request, Portals::uploads($entry, $module)[$key] ?? abort(404));
    }

    public function complete(string $portal, string $module): RedirectResponse
    {
        $entry = $this->authorizedPortal($portal);
        $found = $this->usableModule($entry, $module);

        abort_unless($found['client_can_complete'] ?? false, 403);

        if (Portals::status($found) !== 'complete') {
            Portals::completeModule($entry, $module, User::current());

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

        $container = Portals::container();

        foreach ($request->file('files') as $file) {
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
            $key = now()->format('YmdHis').Str::lower(Str::random(6));
            $path = Portals::uploadFolder($entry, $module)."/{$key}/{$name}.".strtolower($file->getClientOriginalExtension());

            $container->disk()->filesystem()->putFileAs(dirname($path), $file, basename($path));
            Asset::make()->container($container->handle())->path($path)->save();
        }

        $count = count($request->file('files'));
        Activity::record($entry, User::current(), trans_choice('uploaded :count file to “:module”|uploaded :count files to “:module”', $count, ['module' => $found['title'] ?? '']));

        return redirect()->route('client-portal.show', $entry->slug())->with('portal_status', __('Thanks! Your files were uploaded.'));
    }

    public function message(Request $request, string $portal): RedirectResponse
    {
        $entry = $this->authorizedPortal($portal);

        abort_unless($entry->get('comments_enabled'), 404);

        $body = trim($request->validate(['body' => ['required', 'string', 'max:5000']])['body']);
        $user = User::current();

        Portals::addMessage($entry, $user, $body);

        if (Portals::isStaff($user)) {
            Notification::send(Portals::clients($entry), new NewMessage($entry, $user->name() ?: $user->email(), $body));
        } else {
            Activity::record($entry, $user, __('sent a message: “:excerpt”', ['excerpt' => Str::limit($body, 140)]));
        }

        return redirect()->to(route('client-portal.show', $entry->slug()).'#messages')->with('portal_status', __('Message sent.'));
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

    /**
     * Download a private file, or show it in the browser when asked and the type is safe to display.
     */
    private function serve(Request $request, string $path): StreamedResponse
    {
        $filesystem = Portals::container()->disk()->filesystem();

        abort_unless($filesystem->exists($path), 404);

        $previewType = Portals::PREVIEWABLE[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if ($request->boolean('preview') && $previewType) {
            return $filesystem->response($path, basename($path), [
                'Content-Type' => $previewType,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return $filesystem->download($path, basename($path), ['Cache-Control' => 'private, no-store']);
    }
}
