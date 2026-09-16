# Archive

A tiny, self-hosted file browser. Drop files or folders anywhere under this
directory and they become browsable — with thumbnails, previews, and clean
URLs — at `/archive/`.

Everything here is public. Only put things in this folder you're fine with
anyone finding.

## How it works

- **`index.php`** — entry point, just includes the two files below.
- **`_archive/main.php`** — the `Files` class. Resolves the requested path
  from `?p=`, confines it to this directory, and builds either a directory
  listing (with thumbnails) or a single-file view.
- **`_archive/page.php`** — renders the HTML around whatever `Files::get()`
  returns (breadcrumbs, grid of folders/files, or a file preview).

Only files with an approved extension are shown or linked:

```
txt, md, pdf, jpg, jpeg, gif, png, svg, css, js, html, psd, mp4, mov, zip
```

(see `$approved_extensions` in `_archive/main.php`). Anything else on disk is
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
│   ├── main.php            Files class: path resolution, listing, thumbnails
│   ├── page.php            HTML rendering
│   ├── styles.css
│   ├── icon-*.svg          UI icons
│   ├── .htaccess           blocks listing/direct access to this folder
│   └── cache/              generated thumbnails (gitignored)
└── ...                     your archived content lives alongside these
```

Source: https://github.com/donohoe/archive
