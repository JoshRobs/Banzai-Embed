# BanzaiEmbed — Vue & React App Embedder

A WordPress plugin for embedding pre-built Vue, React or vanilla JS apps. Upload the build output as a zip; embed it with `[banzai-embed app="my-app"]` or the **BanzaiEmbed App** block. Nothing is compiled on the server, so it works on any host.

BanzaiEmbed is free and open source under the GPL. Every feature is included — there is no paid tier and no licence key.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Install

1. Download `banzaiembed-{version}.zip` from the [latest release](https://github.com/JoshRobs/Banzai-Embed/releases/latest).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, choose the zip and activate it.
3. Go to **BanzaiEmbed → Add New**, name your app and upload a zip of your build output.
4. Copy the shortcode into any page, or add the **BanzaiEmbed App** block.

BanzaiEmbed is not listed on WordPress.org, so WordPress won't offer updates for it. To update, download the new release and upload it the same way; WordPress offers to replace the installed version, and your apps are kept.

## Features

- **Upload a zip, get a shortcode.** `[banzai-embed app="my-app"]`, or the BanzaiEmbed App block in the block editor.
- **Entry files found for you.** BanzaiEmbed reads your build's `index.html` (or Vite's manifest, or Create React App's `asset-manifest.json`) to load exactly what your build tool intended, hashed filenames and all. If it can't tell, you pick.
- **Your app mounts unmodified.** The mount element gets the same ID your `index.html` used (`#app` for Vite + Vue, `#root` for Vite + React), so the code you already have finds it.
- **Loads only where it's used.** Scripts and styles are enqueued only on pages that embed the app, as ES modules for Vite builds and deferred scripts for webpack builds.
- **Several apps per page.** Each gets its own mount point, and repeated instances get unique IDs.
- **Isolated display.** Show an app in the page, or give it its own page in a frame. Isolated, its CSS can't restyle your theme and your theme's can't restyle it, and a router written for the site root (`/play/…`) works without changes.
- **Data Bridge.** Give your app WordPress data with no PHP: the current post's ID, title, URL or custom fields, the site's name or REST API address, or your own text. Your app reads them from `window.banzaiEmbed['my-app'].data`.
- **Logged-in user data, safe with page caching.** Your app calls `cfg.user()` to get the current visitor's ID, display name, email or roles, and a REST API nonce. It's fetched fresh for each visitor and never written into the page.
- **Environment variables.** Per-app values like API URLs, with separate staging and production values, read from `cfg.env`.
- **Custom CSS & JS.** CSS that loads with the app, and JavaScript that runs before it starts or once it has rendered, without rebuilding it.
- **Site-wide placement.** Show an app on every page with no shortcode (chat widgets, feedback buttons, announcement bars), limited to the post types, pages and visitors you choose.
- **API proxy.** An app built for Netlify or Vercel that calls `fetch('/api/…')` can have those paths forwarded to wherever its backend runs, with no code changes and no CORS setup. Visitors' cookies and WordPress logins are never passed on.
- **Client-side routing.** Put a React Router or Vue Router app on `/portal/`, and links, bookmarks and refreshes on `/portal/settings` load your app instead of a "not found" page. Real child pages keep working.
- **Painless updates.** Upload a new build and every URL changes, so no browser or CDN serves stale files. The previous build is kept for pages cached before the update.
- **Help where you need it.** A ? on every setting explains when to use it, and the Help page starts from the problem you're seeing and points to the fix.

## Preparing an app

Build normally and zip the contents of `dist/` (or the folder itself). Builds made with a root-absolute base — Vite's default `base: '/'`, or a path left over from wherever the app lived before — have their image, font and other asset references pointed at the new location on upload.

A **relative base** is still the most robust choice, and the only one that keeps lazy-loaded chunks working (the plugin warns when a build has them):

| Tool | Setting |
| --- | --- |
| Vite | `base: './'` in `vite.config.js` |
| Create React App | `"homepage": "."` in `package.json` |
| webpack 5 | `output.publicPath: 'auto'` |

The app mounts to the same element ID as in its `index.html`, so an unmodified `createApp(App).mount('#app')` or `createRoot(document.getElementById('root'))` works.

Two things need a small change in the app:

- **Files referenced by a hard-coded absolute path** — e.g. `<use href="/icons.svg#…">` written by hand for something in Vite's `public/`, in a build with a relative base — resolve against the site root and 404. Import the file instead, or prefix it with `window.banzaiEmbed['my-app'].baseUrl`.
- **More than one instance per page** — the entry script runs once. Mount to each ID in `window.banzaiEmbed['my-app'].mounts`.

## Development

The product spec is [CLAUDE.md](CLAUDE.md). How it is built, and where and why it departs from the spec, is in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

The repository root is the plugin. With Docker running:

```bash
npx @wordpress/env start          # http://localhost:8890  (admin / password)
npx @wordpress/env stop
```

Port 8890 keeps it clear of BanzaiStyle's wp-env on 8888.

### Tests

End-to-end, against the wp-env site, through the real admin form:

```bash
# Once: real Vite builds go in tests/fixtures/{vue-rooted,vue-relative,react-relative}
#   npm create vite@latest vue-app -- --template vue   (and --template react)
#   npx vite build                                     → vue-rooted
#   npx vite build --base=./ --manifest                → vue-relative / react-relative
npx @wordpress/env run cli php wp-content/plugins/Banzai-Embed/tests/make-zips.php
bash tests/e2e.sh
npx @wordpress/env run cli wp eval-file wp-content/plugins/Banzai-Embed/tests/make-pages.php
```

`make-zips.php` also synthesises a hostile zip (traversal, PHP, dotfiles), a CRA-style build, a pattern-only build and an unusable one. `e2e.sh` uploads each as an app; `make-pages.php` creates pages that embed them. Check the results with `wp option get banzaiembed_apps --format=json` and by loading the pages.

### Releasing

```powershell
pwsh tools/build.ps1   # → dist/banzaiembed-{version}.zip
```

Bump `Version:` in [banzaiembed.php](banzaiembed.php) (and `BZEM_VERSION`, and `Stable tag` in [readme.txt](readme.txt)), build, then attach the zip to a GitHub release tagged `v{version}`. The zip has a single top-level `banzaiembed/` folder, which is what WordPress's plugin uploader expects.

## License

GPLv2 or later. See [license.txt](license.txt).
