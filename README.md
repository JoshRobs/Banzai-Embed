# BanzaiEmbed — Vue & React App Embedder

A WordPress plugin for embedding pre-built Vue, React or vanilla JS apps. Upload the build output as a zip; embed it with `[banzai-embed app="my-app"]` or the **BanzaiEmbed App** block. Nothing is compiled on the server.

The product spec is [CLAUDE.md](CLAUDE.md). How it is built, and where and why it departs from the spec, is in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Requirements

- WordPress 6.0+
- PHP 7.4+

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

The repository root is the plugin. With Docker running:

```bash
npx @wordpress/env start          # http://localhost:8890  (admin / password)
npx @wordpress/env stop
```

Port 8890 keeps it clear of BanzaiStyle's wp-env on 8888.

### Freemius licensing

Three constants put a local install into Freemius' developer mode. Two are non-secret and live in [.wp-env.json](.wp-env.json): `WP_FS__DEV_MODE` and `WP_FS__SKIP_EMAIL_ACTIVATION`. The third is the plugin's **secret key**, which must not be committed. It goes in `.wp-env.override.json`, which is gitignored and never packaged by `tools/build.ps1`:

```bash
cp .wp-env.override.json.example .wp-env.override.json
# paste the secret key from the Freemius dashboard, then
npx @wordpress/env start
```

wp-env merges the override's `config` block over `.wp-env.json` and regenerates `wp-config.php` on every `start`. Check with `npx @wordpress/env run cli wp config list WP_FS__` (a substring match; it prints the secret key to your terminal).

The constant name embeds the slug, case-sensitively: `WP_FS__banzaiembed_SECRET_KEY`. If the slug passed to `fs_dynamic_init()` is ever different, Freemius silently ignores the key.

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

### Packaging

```powershell
pwsh tools/build.ps1   # → dist/banzaiembed-{version}.zip
```
