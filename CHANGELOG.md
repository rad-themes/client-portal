# Changelog

## 1.1.1 — 2026-10-03

- Security: escape portal titles and every other plain-text field clients see (project status, phase and module titles, descriptions, button labels, file names, login heading and intro). A visitor who registered with HTML in their name could otherwise run script when staff viewed `/portal`. Thanks to the Statamic Marketplace team for the report.
- Requirements now state that Statamic Pro is required: portals need client accounts besides yours and a client role, which Statamic Core doesn't allow.

## 1.1.0 — 2026-10-02

- Add-on pages: other addons can add pages to the portal (at `/portal/-/{page}`, linked from the header) with `RadThemes\ClientPortal\Extensions::page()`. [Alp CRM](https://github.com/rad-themes/alp-crm) uses this to show clients their invoices, quotes, payments and shared files.

## 1.0.1 — 2026-09-30

- Fix: on phones, a gallery module could make the portal wider than the screen
- Screenshots in the README

## 1.0.0 — 2026-09-28

First release, free and open source under the MIT license.

- Private client portals at `/portal`, with login, registration, and forgot/reset password pages
- Phases of modules: link, file download, client upload, content page, image gallery and embed
- Module status (active, inactive, complete), due dates, and a "Client can mark complete" approval button
- Portal progress bar
- Private file storage served only through access checks, with in-browser previews for safe file types
- Messages between clients and your team
- Activity emails (instant or daily digest), due-date reminders, a "Notify clients" action, and per-portal email settings
- Portal templates, an "Apply template" action that keeps client progress, and a portal for each self-registered client
- Branding settings, reCAPTCHA or Cloudflare Turnstile on registration, and import/export commands
- Protection against Control Panel saves undoing client completions
- Works with multisite (portals live in the default site) and any content driver
- Translation-ready (`lang/en.json`)
