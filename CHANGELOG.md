# Changelog

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
