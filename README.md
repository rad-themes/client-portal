# Client Portal for Statamic

Give every client a private, branded portal: project status and progress, phases, files, uploads, embeds, content pages, approvals, reminders and activity emails.

## Requirements

- Statamic 6, PHP 8.3+
- A mail driver, for notifications
- The Laravel scheduler (`php artisan schedule:run` every minute), for reminders and digests

## Installation

```bash
composer require komalnakrani/client-portal
php please client-portal:install
```

The install command creates:

- the **Client Portals** collection and its blueprint (`resources/blueprints/collections/portals/portal.yaml`, yours to customise)
- the private **Portal Files** asset container, stored in `storage/app/client-portal`
- a **client** role
- an example **Website project** template portal (skip it with `--no-example`)
- the portal stylesheet, in `public/vendor/client-portal`

Clients use the portal at **`/portal`**.

## Setting up a portal

1. Create a user for your client, or let clients register themselves (see below).
2. In **Collections → Client Portals**, create an entry, or duplicate a template. Pick the client in **Clients** and add phases and modules.
3. Optionally run the **Notify clients** action on the entry to email its clients.

Clients only see portals they're assigned to. Super users, and roles with the **View Client Portals entries** permission, see all of them.

### Modules

| Module | What the client sees |
|---|---|
| Link | A button to any URL |
| File download | Files from the private container. They're only downloadable through the portal, after an access check. |
| Client upload | An upload form. Files are stored privately and listed on the module in the Control Panel. |
| Content page | A page of rich content, with sidebar navigation between pages |
| Embed | An embedded Figma, Loom, YouTube, Google Docs, Google Calendar or Typeform URL, etc. |

Every module has:

- **Status:** *Active*, *Inactive* (shown as locked and can't be opened) or *Complete*. The portal's progress bar is the share of non-inactive modules that are complete.
- **Due date:** shown on the card; clients get a reminder email before it.
- **Client can mark complete:** adds a button (e.g. "Approve designs") that marks the module complete and notifies you.

### Templates

Turn on **Template** in an entry's sidebar to make it a template. Templates are never shown to clients.

- **New portal from a template:** use Statamic's **Duplicate** action, then turn off *Template* and assign clients.
- **Update many portals at once:** select portals and run **Apply template**. Their phases and modules are replaced with the template's. Progress on modules that exist in both (same ID) is kept: status, completion and uploads.

## Settings

**Tools → Addons → Client Portal → Settings**

- **Branding:** portal name, logo, brand colour, login heading and intro, custom CSS
- **Clients:**
  - *Let clients register themselves* adds a sign-up page at `/portal/register`. New sign-ups get the client role, and their own copy of a template if you choose one.
  - Maximum upload size.
- **Notifications:**
  - Email about client activity (completions, uploads): off, instantly, or as a daily digest.
  - Who receives activity emails. Defaults to all super users.
  - How many days before a due date reminders go out (0 turns them off).

## Scheduled commands

Registered automatically, provided the Laravel scheduler is running:

| Command | When |
|---|---|
| `client-portal:send-reminders` | Daily, 08:00 |
| `client-portal:send-digest` | Daily, 17:00 |

## Import and export

```bash
php please client-portal:export acme-website portal.json
php please client-portal:import portal.json --slug=acme-website-copy
```

Exports leave out clients and client uploads. Imported portals are created unpublished.

## Customising

- **Colour:** use the Brand colour setting, or set `--portal-brand` in custom CSS.
- **Views:** copy any view from the addon's `resources/views` into `resources/views/vendor/client-portal` and edit it there.
- **Reserved URLs:** `/portal/login`, `/portal/register`, `/portal/forgot-password` and `/portal/reset-password`. Don't give a portal one of those slugs.

## Security notes

- Every portal page, download, upload and completion checks that the user is assigned to the portal.
- Portal files live outside the web root and are only served through the access check.
- Client uploads are limited by type (documents, images, audio/video, design files and archives, but no HTML, SVG or scripts) and by size.
- Portals send `noindex`. If you use static caching, exclude `/portal*` from it.

## Development

```bash
composer install
vendor/bin/phpunit
npm run build   # rebuilds resources/dist/portal.css
```
