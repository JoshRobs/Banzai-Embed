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
  class-site-wide__premium_only.php   Pro: site-wide placement and its rules (not in the free build)
  class-data-bridge__premium_only.php Pro: cfg.data, cfg.env, cfg.user() and the admin card behind them
  class-custom-code__premium_only.php Pro: per-app CSS, and JS before/after the app
vendor/freemius/                Freemius SDK (tracked; ships in the zip)
blocks/app/block.json           block metadata (editor script registered by handle — no build step)
templates/                      admin screens
assets/js, assets/css           admin.js, block.js (plain ES5, wp.* globals), styles
tests/                          fixture builder, e2e upload script, front-end pages
tools/build.ps1                 allowlist packager
```

Naming follows BanzaiStyle's actual convention (not the spec's `banzaiembed_` everywhere): namespace `BanzaiEmbed\`, global functions `bzem_`, constants `BZEM_`, hooks `bzem/…`, handles and CSS classes `bzem-`. The slug, text domain, option and uploads folder are `banzaiembed`.

## Data

One autoloaded option, `banzaiembed_apps`, keyed by slug. The record shape is `App_Manager::defaults()`; anything missing from a stored record is filled from it, so adding a field needs no migration. Records saved before `active` and `modified` existed come back active, with `App_Manager::modified()` falling back to the upload or creation time.

`active` is deliberately separate from `App_Manager::status()`: status says whether a build *can* be embedded (`ready`, `needs-entry`, `no-build`), active says whether the site owner *wants* it embedded right now.

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

`id`, `class` and `style` attributes are sanitised (`[A-Za-z0-9_-]`, `sanitize_html_class`, `safecss_filter_attr`) because shortcodes can be written by Contributors. Problems (unknown slug, no build, app switched off) are shown to users who can edit posts and render nothing for visitors. An inactive app is also skipped by `prescan()`, so none of its assets load.

## Admin screens

Both screens share a brand bar and framework tabs (`templates/admin-header.php`), printed above `.wrap`; the `bzem-admin-page` body class, added only on our two screens (not Freemius' pages in the same menu), removes the content gutter so the bar runs edge to edge. The list is filtered, searched and sorted server-side from query arguments (`framework`, `status`, `s`, `orderby`, `order`) — there are few enough apps that `WP_List_Table`'s pagination machinery would be dead weight, but the markup keeps its classes so the core mobile layout applies.

The on/off switch on each row is a tiny form posting to admin-post.php (`Admin::handle_toggle()`), so it works without JavaScript; admin.js submits the same form with `ajax=1` and gets JSON instead of a redirect. Read the form's URL with `getAttribute('action')`: its `<input name="action">` shadows `form.action`.

## Pro

Freemius is initialised at the top of banzaiembed.php as `bzem_fs()` (product 40624, slug `banzaiembed`), with BanzaiStyle's structure: the rest of the file sits in the `else` of `function_exists( 'bzem_fs' )`, so when the free and premium copies are both active the second one only calls `set_basename()` instead of redeclaring every function. `tools/build.ps1` builds the premium zip; Freemius generates the free one from it.

`bzem_has_valid_license()` is the single gate: `License::is_valid()` asks `bzem_fs()->can_use_premium_code()` and fails closed if the SDK is missing. `BZEM_SIMULATE_PRO` in wp-config.php forces it open (`true`) or closed (`false`) for development, whatever the real licence says. `License::PRO_AVAILABLE` is true now the plan is on sale, so the free build shows upsell cards linking to the Freemius pricing page; set it back to false if the plan is ever withdrawn.

### Keeping premium code out of the free build

wp.org's guideline 5 forbids locked functionality in the free plugin, so premium code must not be *in* it. Freemius builds the free version from the premium zip by dropping files whose names contain `__premium_only` and stripping `if ( bzem_fs()->is__premium_only() ) { … }` blocks. Each Pro feature is therefore one `*__premium_only.php` class (plus its template), loaded from a single such block in `Plugin::run()`, and plugs in through hooks the free code fires anyway: `bzem/save_app`, `bzem/edit_placement`, `bzem/edit_cards`, `bzem/app_data`, `bzem/enqueued`. Nothing else in the free code may name a premium class.

Shared data (record fields like `placement`, `rules`) and read-only display (the list's Placement column) stay in the free code: they describe what is stored, and an app left site-wide after switching back to the free build must still be shown honestly.

### Site-wide placement

`Site_Wide` (`includes/class-site-wide__premium_only.php`) prints apps whose `placement` is `site_wide` on every front-end page their rules match — no shortcode or block. Matching apps are worked out once per request: their assets are enqueued on `wp_enqueue_scripts` (CSS in `<head>`) and their mount points printed on `wp_footer` at priority 5, through `Embed::render()`, so `window.banzaiEmbed`, unique mount IDs and active/inactive all behave as for an embedded app. Nothing prints in wp-admin, feeds, oEmbed or JSON requests.

Rules (`App_Manager::rules()`): post types (their *singular* views; empty means every page, archives and 404s included), excluded pages (matched against the queried page, or the posts page on a static-front-page site — never a term ID), and audience (everyone, logged-in, logged-out). `bzem/site_wide_matches` can override the result.

Rendering checks that the premium code is present, **not** that the licence is valid: if a licence lapses, apps already placed site-wide keep showing, because a chat widget vanishing from a client's whole site overnight is the worst way to learn about a renewal. What needs a licence is *changing* placement to site-wide or editing rules — enforced in `Site_Wide::save()`, not just by the disabled form controls. Moving an app back to shortcode is always allowed. Whether Freemius still reports an expired licence as `can_use_premium_code()` depends on the plan's settings in the Freemius dashboard.

There is no uninstall.php. WordPress runs that file *instead of* a registered uninstall hook, and Freemius reports uninstalls through one, so its presence would hide every uninstall from Freemius. Cleanup is `bzem_uninstall()`, on Freemius' `after_uninstall` action.

Data Bridge and Custom CSS & JS follow the same licence rule as site-wide placement: what is saved keeps reaching the front end if a licence lapses (an app built to read `cfg.data` would otherwise break overnight), and only saving changes needs a licence — enforced in each module's `save()`. Unlicensed, their cards render with an info notice and a disabled `<fieldset>`; a disabled fieldset posts nothing, not even the card's marker field, so the saved settings are untouched. Beyond the hooks above they use `bzem/enqueued` (inline JS before the app) and `bzem/edit_cards` (cards below the entry files).

### Data Bridge (and environment variables)

`Data_Bridge` — spec items 7 and 10 in one card, stored as `bridge` on the record (`{ values, env, user }`). The app sees:

```js
cfg.data.planName      // page data: text, post fields/custom fields, site fields
cfg.env.API_URL        // environment variables
await cfg.user()       // { loggedIn, id?, displayName?, email?, roles?, nonce? }
```

Split by whether a page cache may store the value:

- **Page data and env are inlined** via `bzem/app_data`. They are the same for every visitor to a URL. Post values come from the queried object on singular views and are `null` elsewhere — an embed in an archive's widget gets nothing rather than whichever post was in the loop. Custom fields: protected (`_`-prefixed) keys are refused, and none are output for a password-protected post.
- **User data is fetched**, never inlined: a page cache would serve one user's email to the next visitor, and a cached REST nonce goes stale. `cfg.user()` calls `admin-ajax.php?action=bzem_user&app={slug}` (no-store). Not a REST route, because REST treats a cookie without a nonce as logged out — and the nonce is what is being fetched. The URL is relative so it is always same-origin with the page and the login cookie goes along; the same-origin policy stops other sites reading it. The promise is shared and refetched after an hour, on `cfg.user(true)`, or after a failure. `bzem/user_data` filters the payload (the place for user meta).
- **Env**: the staging value is used whenever `wp_get_environment_type()` is not `production`, falling back to the production value. Values are strings.
- The data object is encoded with `JSON_HEX_TAG | JSON_HEX_AMP`. Custom fields are writable by authors, and `<!--<script>` inside an inline script derails the HTML parser.
- Keys must be JS identifiers (`cfg.data.key` must work); invalid, duplicate and over-cap (50) rows are dropped with a notice listing why.

**Custom PHP snippets** (in the spec) are deliberately not built: they are `eval()` of admin-entered code, which wp.org reviewers and security scanners flag. `bzem/app_data` and `bzem/user_data` in a site's own code do the same job.

### Custom CSS & JS

`Custom_Code` — spec item 9, stored as `custom_code` on the record (`{ css, js_before, js_after }`), edited in WordPress's code editor (CodeMirror via `wp_enqueue_code_editor()`, which falls back to plain textareas when the user has syntax highlighting off).

- **CSS** goes on an empty style handle that depends on the app's stylesheets, so it prints after them and wins at equal specificity. It lands in `<head>` whenever the app's own CSS does (see `prescan()`). It is not scoped automatically — the card tells the admin to start selectors with `.bzem-app-{slug}`, which every mount element has.
- **JS before** is an inline script before the app's first script, hooked after the Data Bridge so `cfg.user()` already exists.
- **JS after** cannot be an inline script after the app's tag: modules and deferred scripts execute after parsing, so it would run *first*. Instead it waits for `DOMContentLoaded` (by which point the entry has executed), then for the first mount element to get content (a `MutationObserver`, since React and Vue render asynchronously). If the element is missing or already has content, it runs at `DOMContentLoaded`. Once per page, however many instances.
- Both JS snippets run inside `function(cfg){…}`, so `cfg` is to hand and variables stay local. Code goes in on newlines so a trailing `//` comment cannot swallow the closing brace.
- `</script` and `</style` are rewritten to `<\/script` / `<\/style`. That means the same inside a JS string, regex or comment and a CSS string; anywhere else the code was already broken. Nothing else is filtered: only users with `unfiltered_html` can save it.
- 50 KB per field (it is in an autoloaded option); an oversized field keeps its previous value and the admin is told.

In the free build, when `License::PRO_AVAILABLE` is true, the edit screen shows upsell cards for Data Bridge and Custom CSS & JS in their place (as it does for Placement).

### Still to build

- **Routing** — rewrite rules need flushing on change and must not swallow real child pages; plan for it as its own piece of work.

## What is not built

From the spec's free scope, deliberately left out:

- **"Use WordPress's bundled React" option.** A Vite or CRA build bundles React already; using `wp-element` instead requires externalising React at build time, which is a build-config decision the plugin cannot make for the developer. Revisit as a generic "script dependencies" field if asked for.
- **AJAX upload.** Form posts to admin-post.php instead (see `Admin`).
