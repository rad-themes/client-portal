# Client Portal for Statamic

Give every client a private portal with project status, phases and modules: links, file downloads and content pages.

## Installation

```bash
composer require komalnakrani/client-portal
php please client-portal:install
```

The install command creates:

- the `portals` collection and its blueprint (`resources/blueprints/collections/portals/portal.yaml`)
- a `client` role
- the portal stylesheet in `public/vendor/client-portal`

## Usage

1. Create a user for your client.
2. In the Control Panel, create a **Client Portal** entry, pick the client in the **Clients** field, and add phases and modules.
3. Your client logs in at `/portal` and sees only the portals they're assigned to. Super users and users with the `view portals entries` permission see all of them.

### Modules

| Module | Shows |
|---|---|
| Link | A button to any URL |
| File download | One or more assets |
| Content page | A page of rich content with sidebar navigation |

Every module has a status (**Active**, **Inactive**, **Complete**) and an optional due date. Inactive modules are visible but can't be opened.

## Customising

- Brand colour: set `--portal-brand` in your own CSS, or publish the views.
- Views: copy any view from the addon's `resources/views` into `resources/views/vendor/client-portal` and edit it there.

## Development

```bash
composer install
vendor/bin/phpunit
npm run build   # rebuilds resources/dist/portal.css
```
