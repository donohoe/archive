# Archive

A tiny, self-hosted file browser. Drop files or folders anywhere under this
directory and they become browsable — with thumbnails, previews, and clean
URLs — at `/archive/`.

Everything here is public. Only put things in this folder you're fine with
anyone finding.

## How it works

- **`index.php`** — entry point, includes the three files below in order.
- **`_archive/config.php`** — the only file meant to be hand-edited: noindex
  toggle, timezone, approved extensions, thumbnail width.
- **`_archive/main.php`** — the `Files` class. Resolves the requested path
  from `?p=`, confines it to this directory, and builds either a directory
  listing (with thumbnails) or a single-file view.
- **`_archive/page.php`** — renders the HTML around whatever `Files::get()`
  returns (breadcrumbs, grid of folders/files, or a file preview).

Only files with an approved extension are shown or linked:

```
txt, md, pdf, jpg, jpeg, gif, png, svg, css, js, html, psd, mp4, mov, zip
```

(see `ARCHIVE_APPROVED_EXTENSIONS` in `_archive/config.php`). Anything else on disk is
invisible to the app. Files and folders whose name starts with `.` or `_`
are also skipped in listings — that's how `_archive/` itself, and files like
`.DS_Store`, stay hidden. Thumbnails (for jpg/png/gif) are generated on
first view and cached in `_archive/cache/`.

## URL structure

Requests are routed by the `.htaccess` at the site root:

- `/archive/some-folder/` → directory listing (internally `index.php?p=some-folder/`)
- `/archive/some-folder/file.jpg` → served directly as a static file when it
  exists on disk and has an approved extension; otherwise (or when viewed
  through the app, e.g. clicking into it) it goes through `index.php` for
  the file-detail/preview page
- `/archive/_archive/...` → never routed to the app; blocked outright by
  `_archive/.htaccess` (no directory listing, no direct `.php` access)

The `?p=` query string still works as a fallback (it's what the app reads
internally), but every link the app generates uses the clean `/archive/...`
form.

## Requirements

- Apache with `mod_rewrite` (the rewrite rules live in the site root
  `.htaccess`, not inside this folder)
- PHP with the GD extension (for thumbnail generation)

## Deployment — action needed on every server

The site-root `.htaccess` lives **outside this repo** (it's the WordPress
install's own `.htaccess`, shared with other things on the site), so it
doesn't travel with a deploy of this folder. Every server this runs on
needs this checked/added manually.

Without it, a request for an existing file (e.g. `/archive/README.md`)
gets routed through `index.php` instead of served directly — which renders
the full page, including a preview `<iframe>` pointing back at that same
URL, which does the same thing again: infinite nested iframes. (This bit
us on production after the fix had only been applied locally.)

In the site root's `.htaccess`, immediately before the line
`RewriteRule ^archive/(.*)$ /archive/index.php?p=$1 [QSA,L]`, there should
be a block that lets existing files fall through to Apache's normal static
serving instead of being swallowed by that rewrite:

```apache
RewriteCond %{REQUEST_URI} ^/archive/(.+)$
RewriteCond %{REQUEST_URI} !^/archive/_archive/
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_URI} \.(txt|md|pdf|jpe?g|gif|png|svg|css|js|html|psd|mp4|mov|zip)$ [NC]
RewriteRule ^archive/(.*)$ - [L]
```

Keep that extension list in sync with `ARCHIVE_APPROVED_EXTENSIONS` in
`_archive/config.php`. Check this is present any time the archive is
deployed somewhere new, or if files stop loading / a page starts loading
itself repeatedly in an iframe.

## Adding content

Just add a folder or file anywhere under this directory. Nothing to
register — the listing is generated from whatever's on disk. Prefix a
file or folder name with `.` or `_` to keep it out of the browsable
listings (see `_archive/` for an example).

## Security notes

- Path resolution uses `realpath()` and confines every request to this
  directory — traversal attempts (`../../etc/passwd`, encoded variants,
  etc.) are rejected.
- `_archive/` (the app's own code, styles, and thumbnail cache) is walled
  off from both the app's router and direct web access.
- This is a single-owner archive: anything placed here is assumed to be
  content the owner is fine publishing. In particular, `.svg` and `.html`
  are on the approved list and are served/rendered same-origin, so don't
  drop untrusted files of those types in here.
- There's no authentication — "public" is the point. If that ever needs to
  change, it'll need to happen in front of this app (e.g. HTTP auth in
  `.htaccess`), not within it.

## Project layout

```
archive/
├── index.php              entry point
├── .gitignore
├── _archive/               app internals (blocked from public browsing)
│   ├── config.php          settings: noindex, timezone, extensions, thumbnail width
│   ├── main.php            Files class: path resolution, listing, thumbnails
│   ├── page.php            HTML rendering
│   ├── styles.css
│   ├── icon-*.svg          UI icons
│   ├── .htaccess           blocks listing/direct access to this folder
│   └── cache/              generated thumbnails (gitignored)
└── ...                     your archived content lives alongside these
```

Source: https://github.com/donohoe/archive
