# Architecture

How BanzaiEmbed is put together, and the decisions behind it that are not obvious from the code — several of which depart from the original spec in [CLAUDE.md](../CLAUDE.md) on purpose.

## Layout

```
banzaiembed.php                 bootstrap: Freemius (bzem_fs), constants, autoloader, bzem_has_valid_license(),
                                plugins_loaded, bzem_uninstall() on Freemius' after_uninstall
includes/
  class-plugin.php              wires everything; fires bzem/init for pro modules
  class-app-manager.php         the banzaiembed_apps option, paths/URLs of builds, status, mount ID
  class-uploader.php            zip → build directory; the security boundary
  class-asset-detector.php      finds entry JS/CSS + mount ID + compiled base in a build
  class-path-rewriter.php       points a build's asset references at its new location
  class-embed.php               mount div + enqueuing + window.banzaiEmbed; shared by shortcode and block
  class-shortcode.php           [banzai-embed] → Embed::render()
  class-block.php               banzaiembed/app → Embed::render(); editor data
  class-admin.php               menu, list/edit screens, admin-post handlers, notices
  class-filesystem.php          the only code that touches the disk (WP_Filesystem_Direct)
  class-license.php             Freemius seam; fails closed
vendor/freemius/                Freemius SDK (tracked; ships in the zip)
blocks/app/block.json           block metadata (editor script registered by handle — no build step)
templates/                      admin screens
assets/js, assets/css           admin.js, block.js (plain ES5, wp.* globals), styles
tests/                          fixture builder, e2e upload script, front-end pages
tools/build.ps1                 allowlist packager
```

Naming follows BanzaiStyle's actual convention (not the spec's `banzaiembed_` everywhere): namespace `BanzaiEmbed\`, global functions `bzem_`, constants `BZEM_`, hooks `bzem/…`, handles and CSS classes `bzem-`. The slug, text domain, option and uploads folder are `banzaiembed`.

## Data

One autoloaded option, `banzaiembed_apps`, keyed by slug. The record shape is `App_Manager::defaults()`; anything missing from a stored record is filled from it, so adding a field needs no migration.

The slug cannot change after creation: shortcodes, blocks, the uploads path and `window.banzaiEmbed` keys all use it.

## Builds and cache-busting

Every upload goes to a **new directory**: `uploads/banzaiembed/{slug}/{YmdHis-random}/`. The record switches to it only after extraction succeeds, so a failed upload leaves the live app untouched. The previous build is kept (and older ones pruned) so pages cached by a page cache or CDN before the upload keep loading until they expire.

Because every URL changes on every upload — entry, lazy chunks, fonts, images — no `?ver=` is added to anything. That is not just redundant but harmful for ES modules: the browser keys modules by full URL, so `index.js?ver=x` (our tag) and `index.js` (another chunk's `import`) would be two instances and the app would boot twice.

## Upload security

Uploading an app is uploading JavaScript that runs for every visitor, into a web-reachable directory. So:

- **Capability:** `manage_options` **and** `unfiltered_html` (`Admin::can_manage()`). On multisite, site admins lack `unfiltered_html`; without the second check this screen would bypass that.
- **No PHP ever reaches the disk.** Entries are read one at a time from the zip (ZipArchive, or WordPress's bundled PclZip as a fallback) and only allowlisted static types are written (`Uploader::ALLOWED_EXTENSIONS`, filterable via `bzem/allowed_extensions`). There is no extract-to-temp step, so there is no window in which a `.php` file exists under uploads/.
- Any name containing a PHP-like extension anywhere (`shell.php.png`) is refused — Apache with a loose `AddHandler` would execute it.
- Traversal (`..`), absolute paths, drive letters, NUL bytes and dotfiles (`.htaccess`, `.user.ini`) are refused. `.vite/` is the one dot-directory allowed, for Vite's manifest.
- Caps on file count and uncompressed size (`bzem/max_files`, `bzem/max_bytes`) stop zip bombs.
- A zip of the `dist/` folder itself (one wrapper directory) is unwrapped; `__MACOSX/` is dropped; Windows `\` separators are normalised.

No `.htaccess` is written to the uploads folder: with a restrictive `AllowOverride` an unknown directive turns every request in the directory into a 500, which would take every app down. The allowlist is the control.

## Entry detection

`Asset_Detector::detect()`, first hit wins:

1. **index.html** — the `<script src>` and `<link rel=stylesheet>` tags the build tool wrote, in order. `nomodule` scripts (Vite's legacy plugin) are skipped; inline scripts are reported.
2. **Vite manifest** — `.vite/manifest.json` (Vite 5+) or `manifest.json` (older), recognised by chunk objects with `file`/`isEntry` so a PWA `manifest.json` is not mistaken for it. CSS of statically imported chunks is included.
3. **asset-manifest.json** — `entrypoints` from Create React App / webpack. Classic scripts.
4. **Filename patterns** — runtime, vendor, then index/main/app; the largest match wins, because Vite names lazy route chunks `index-*.js` too.

Root-absolute references (`/assets/x.js` for Vite's default `base: '/'`, or `/old/site/plugins/app/assets/x.js` for a build made for somewhere else) are resolved by finding the file in the build, and the prefix in front of it is reported as the build's `base`.

## Relocating builds compiled for another path

A build with a root-absolute base has that base baked into its JS and CSS — `url(/old/path/assets/Font.woff2)`, `` `/assets/hero.png` `` — and every one of those 404s under WordPress. When detection reports a base, `Path_Rewriter` rewrites them at upload time, in place, to the build's new location (root-relative, or the full URL when uploads are on another host).

Only exact `{base}{file}` references to files that are **in the build** are rewritten. A bare base, or a path that is not one of the build's files, is left alone: `/app/` may be a router basename and `/assets/data` an API route on the site, and rewriting those would break the app in ways far harder to diagnose than a missing image.

What remains unfixed is URLs built at runtime from the bare base string — Vite's preload helper (`return"/"+e`) and `import.meta.env.BASE_URL`. These only matter for lazy-loaded chunks, so the "rebuild with `base: './'`" warning is shown only when the build has JS beyond its entry files (`Path_Rewriter::needs_rebuild()`), and is recomputed when the entries change.

Found on a real build: one compiled with `base: '/josh_staging_neu/wp-content/plugins/neudayquestionnaire/'` (its previous home) had 15 references to fonts and images in its CSS and JS; all 404'd before relocation and all load after.

The mount ID comes from the first `id` on a `div`/`main`/`section` in index.html's `<body>` — `app` for Vite + Vue, `root` for Vite + React and CRA. **This is the default mount ID**, not the spec's generated `bzem-{slug}`: it is what the unmodified app's code targets. `bzem-{slug}` is only the fallback when there is no index.html.

## Rendering

`Embed::render()` is the one implementation behind the shortcode and the block. It prints

```html
<div id="app" class="bzem-app bzem-app-{slug} …" data-bzem-app="{slug}" style="…"></div>
```

and enqueues the app's assets (once per request per app). Scripts go in the footer, chained by dependency so they keep their order, and `script_loader_tag` turns them into `type="module"` or `defer` tags. Styles are enqueued early by `Embed::prescan()` on `wp_enqueue_scripts` when the queried post's content contains the shortcode or block (nested blocks included), so CSS lands in `<head>`; embeds elsewhere (widgets, templates) still work but their CSS prints in the footer.

Before the first script, each app gets:

```js
window.banzaiEmbed["my-app"] = { slug, baseUrl, mountId, mounts: ["app", "app-2"] }
```

- `mounts` — every instance on the page. Repeated instances get suffixed IDs; the entry script runs once, so an app that wants several instances loops over this.
- `baseUrl` — the build folder URL, for files the app references by path at runtime (anything from Vite's `public/` written as `/icons.svg` will otherwise 404).
- Keyed by slug verbatim, not camel-cased as the spec sketched: `my-app` and `my_app` would collide as `myApp`.
- `bzem/app_data` filters the object — the seam for the pro Data Bridge and environment variables.

`id`, `class` and `style` attributes are sanitised (`[A-Za-z0-9_-]`, `sanitize_html_class`, `safecss_filter_attr`) because shortcodes can be written by Contributors. Problems (unknown slug, no build) are shown to users who can edit posts and render nothing for visitors.

## Pro

Freemius is initialised at the top of banzaiembed.php as `bzem_fs()` (product 40624, slug `banzaiembed`), with BanzaiStyle's structure: the rest of the file sits in the `else` of `function_exists( 'bzem_fs' )`, so when the free and premium copies are both active the second one only calls `set_basename()` instead of redeclaring every function. `tools/build.ps1` builds the premium zip; Freemius generates the free one from it.

`bzem_has_valid_license()` is the single gate: `License::is_valid()` asks `bzem_fs()->can_use_premium_code()` and fails closed if the SDK is missing. `BZEM_SIMULATE_PRO` in wp-config.php opens it for development. `License::PRO_AVAILABLE` is still false — there are no pro features to sell yet, so no pro badges show.

There is no uninstall.php. WordPress runs that file *instead of* a registered uninstall hook, and Freemius reports uninstalls through one, so its presence would hide every uninstall from Freemius. Cleanup is `bzem_uninstall()`, on Freemius' `after_uninstall` action.

Notes for the pro features as specced:

- **Data Bridge — current user data.** Anything per-user printed into the page HTML is cached by page caches and served to the next visitor: one user's email in another's page. Per-user values (and the REST nonce, which goes stale in a cached page) should come from a REST endpoint the app calls, not be inlined. Page-level values (post ID, title, REST base URL) are safe to inline via `bzem/app_data`.
- **Custom PHP snippets** are `eval()` of admin-entered code. Recommend dropping it: it is a remote code execution feature that wp.org reviewers and security scanners will flag, and a `bzem/app_data` filter in a site's own code does the same job.
- **Routing** — rewrite rules need flushing on change and must not swallow real child pages; plan for it as its own piece of work.

## What is not built

From the spec's free scope, deliberately left out:

- **"Use WordPress's bundled React" option.** A Vite or CRA build bundles React already; using `wp-element` instead requires externalising React at build time, which is a build-config decision the plugin cannot make for the developer. Revisit as a generic "script dependencies" field if asked for.
- **AJAX upload.** Form posts to admin-post.php instead (see `Admin`).
