# BanzaiEmbed — Vue & React for WordPress

## Overview

A WordPress plugin that lets developers embed pre-built Vue and React applications into any WordPress page or post. The developer builds their app locally (as they normally would), uploads the build output through the plugin's admin interface, and embeds it anywhere using a shortcode or Gutenberg block.

No server-side build tools required. No Node.js on the host. Works on any WordPress hosting — shared, managed, or dedicated. The plugin handles script/style enqueuing, mount point creation, and isolation so the embedded app doesn't conflict with WordPress or other plugins.

## Why This Exists

The current options for embedding a modern JS app in WordPress are:
- **ReactPress** (3K installs) — requires Node.js on the server, React only, no Vue support, stale updates, compatibility issues with recent WordPress
- **Manual shortcode approach** — every blog post and tutorial shows a different hacky method, nothing standardized
- **Headless WordPress** — overkill when you just want one interactive widget on an otherwise normal WordPress site

BanzaiEmbed fills the gap: a clean, zero-dependency way to drop a Vue or React app into WordPress that works everywhere.

## Target User

WordPress developers and agencies who build client sites in WordPress but need interactive components (calculators, configurators, dashboards, booking widgets, data visualizations, account portals) that are better built in Vue or React than in vanilla JS or jQuery. They already know how to build and bundle a frontend app — they just need a clean way to get it into WordPress.

## Architecture

- **Type:** WordPress plugin (standard WP plugin structure)
- **Admin interface:** Settings page for managing uploaded apps (list, upload, configure, delete)
- **Frontend output:** Shortcode `[banzai-embed]` and Gutenberg block that render the app's mount point and enqueue its assets
- **Storage:** Uploaded build files stored in `wp-content/uploads/banzaiembed/` organized by app slug
- **No external dependencies:** No Node.js, no npm, no build tools on the server

## MVP Scope — Free Version

### 1. App Management Admin Page

An admin page under a top-level "BanzaiEmbed" menu item (or under the Banzai brand menu if you're sharing one with BanzaiStyle).

**App list view:**
- Table showing all uploaded apps with columns: Name, Framework (Vue/React), Shortcode, Date Uploaded, Status
- Each row has actions: Edit, Delete, Copy Shortcode

**Add new app flow:**
- Form fields:
  - **App Name** (required) — human-readable name, used to generate the slug
  - **App Slug** (auto-generated from name, editable) — used in the shortcode: `[banzai-embed app="my-app"]`
  - **Framework** (dropdown: Vue 3, React 18+, Other/Vanilla) — helps the plugin handle mounting correctly
  - **Mount Element ID** (optional, defaults to auto-generated) — the DOM element ID the app mounts to. For Vue apps this is what you pass to `app.mount()`, for React it's the `createRoot()` target. If left blank, the plugin generates one like `bzem-my-app`
- **Upload zone:**
  - Accepts a zip file containing the build output (the contents of `dist/` or `build/`)
  - On upload, the plugin extracts the zip into `wp-content/uploads/banzaiembed/{app-slug}/`
  - The plugin scans the extracted files to identify the entry JS and CSS files (looks for `index.js`, `main.js`, `app.js` patterns, and similarly for CSS — or parses the `index.html` if present to find the actual asset references)
  - Shows the detected entry files and lets the user confirm or manually select them if auto-detection fails

### 2. Asset Detection and Enqueuing

This is the core technical challenge. Modern build tools (Vite, webpack, etc.) produce hashed filenames like `index-3a4b5c.js`. The plugin needs to reliably find and enqueue the right files.

**Detection strategy (in priority order):**
1. Parse `index.html` if present — extract `<script>` and `<link>` tags to find the actual asset paths. This is the most reliable method since it's what the build tool intended.
2. Parse a manifest file if present — Vite generates `.vite/manifest.json`, webpack can generate `asset-manifest.json`. These map entry points to hashed filenames.
3. Filename pattern matching — look for JS/CSS files matching common patterns (`index*.js`, `main*.js`, `app*.js`, `chunk-vendors*.js`)
4. Manual selection — let the user pick which JS and CSS files to load via the admin UI

**Enqueuing:**
- Register and enqueue the detected JS and CSS files only on pages/posts where the shortcode or block is used (not globally)
- Handle script dependencies: if the framework is React and WordPress already ships React (it does via `wp-element`), give the user the option to use WP's bundled React or their own. Default to using their own to avoid version conflicts.
- Enqueue scripts with `defer` to avoid blocking page render
- Support multiple apps on the same page (different shortcodes, different mount points)

### 3. Shortcode

`[banzai-embed app="my-app"]`

When rendered:
1. Creates a `<div id="{mount-element-id}"></div>` where the app will mount
2. Enqueues the app's JS and CSS files
3. The app boots and mounts to the div as it normally would

**Optional shortcode attributes:**
- `id` — override the mount element ID for this instance
- `class` — add CSS classes to the container div
- `style` — inline styles on the container (useful for setting height/width)

### 4. Gutenberg Block

A "BanzaiEmbed" block that provides the same functionality as the shortcode but with a visual interface in the block editor.

**Block editor view:**
- Dropdown to select from uploaded apps
- Preview showing the app name and a placeholder (not a live preview — that's too complex for V1)
- Settings sidebar with the same options as shortcode attributes (custom ID, classes, styles)

**Frontend rendering:**
- Outputs the same HTML as the shortcode

### 5. App Isolation

Embedded apps should not break WordPress or vice versa. Basic isolation measures:

- The mount div gets a unique ID to avoid conflicts
- The plugin does NOT add any global CSS reset inside the mount point (this would break apps that expect to inherit styles). Instead, document clearly that the app should include its own styles
- If two instances of the same app appear on one page, the plugin generates unique mount IDs for each and the app's entry script should handle mounting to the provided ID

### 6. App Updates

When the developer rebuilds their app (new features, bug fixes), they need to update it in WordPress:

- On the app's edit page, show a "Replace Build" upload zone
- Uploading a new zip replaces the contents of the app's directory
- The old files are deleted, new files extracted
- Asset detection runs again on the new files
- Add a cache-busting version query parameter to enqueued assets so browsers pick up the new files

## MVP Scope — Pro Features

Gate these behind Freemius license check, same pattern as BanzaiStyle.

### 7. Data Bridge — WordPress to App (Pro)

The killer pro feature. Pass WordPress data into the embedded app via a global JS object:

- In the admin UI for each app, a "Data Bridge" section lets the user define key-value pairs
- Values can be static strings, or dynamic WordPress data:
  - Current user info (ID, name, email, role) — with a privacy notice
  - Current post/page info (ID, title, URL, custom fields)
  - WordPress REST API base URL and nonce (so the app can make authenticated API calls)
  - Custom PHP snippets that return a value (advanced)
- The plugin uses `wp_localize_script` to inject this data as a JS object: `window.banzaiEmbed.myApp = { userId: 1, apiBase: '...', nonce: '...' }`
- This is the feature that makes BanzaiEmbed genuinely powerful — a React dashboard widget can access the current user's data without any custom plugin code

### 8. Multi-Page App Routing (Pro)

For single-page apps with client-side routing (Vue Router, React Router):

- Option to designate an app as "routed"
- The plugin generates WordPress rewrite rules so that subpaths of the page where the app is embedded are passed to the app instead of triggering WordPress 404s
- Example: if the app is on `/portal/`, then `/portal/settings` and `/portal/profile` all load the same WordPress page and let the client-side router handle it

### 9. Per-App Custom CSS/JS (Pro)

- Add custom CSS that wraps the app's mount div (useful for sizing, positioning, responsive overrides)
- Add custom JS that runs before or after the app initializes (useful for configuration, event listeners, analytics hooks)

### 10. Environment Variables (Pro)

- Define per-app environment variables in the admin UI
- Injected as `window.banzaiEmbed.myApp.env = { API_URL: '...', FEATURE_FLAG: true }`
- Separate values for staging vs production if the site uses a staging environment

## Out of Scope for V1

- Server-side rendering (SSR) — this is a fundamentally different architecture
- Building/compiling apps on the server — the whole point is you build locally
- Live preview in the Gutenberg editor — too complex, show a placeholder instead
- Angular, Svelte, or other framework-specific support — Vue and React cover the vast majority; "Other/Vanilla" handles everything else
- Elementor widget version — possible later but shortcode + Gutenberg block covers all page builders via their shortcode/HTML widgets
- Theme integration or template parts — shortcode is the universal mechanism
- Visual app builder or no-code interface — not the target user

## Technical Notes

### Plugin Structure

```
banzaiembed/
├── banzaiembed.php              # Main plugin file (plugin header, activation, hooks)
├── readme.txt                    # WordPress.org readme
├── assets/
│   ├── js/
│   │   ├── admin.js             # Admin page interactions (upload, app management)
│   │   └── block.js             # Gutenberg block registration and editor UI
│   └── css/
│       ├── admin.css            # Admin page styles
│       └── block-editor.css     # Block editor styles
├── includes/
│   ├── class-banzaiembed-plugin.php    # Main plugin class
│   ├── class-banzaiembed-app-manager.php    # CRUD for apps (add, update, delete, list)
│   ├── class-banzaiembed-asset-detector.php # Logic for finding entry JS/CSS in uploaded builds
│   ├── class-banzaiembed-shortcode.php      # Shortcode registration and rendering
│   ├── class-banzaiembed-block.php          # Gutenberg block registration
│   └── class-banzaiembed-uploader.php       # Zip upload, extraction, file management
└── templates/
    └── admin-page.php           # Admin page HTML template
```

### Prefix

All functions, classes, constants, options, and hooks use the `banzaiembed_` prefix. This is consistent with BanzaiStyle using `banzaistyle_` as its prefix.

### File Storage

Apps stored at: `wp-content/uploads/banzaiembed/{app-slug}/`

Each app's metadata (name, slug, framework, entry files, mount ID, upload date) stored as a WordPress option: `banzaiembed_apps` (serialized array of app configs).

### WordPress Hooks

- `admin_menu` — register the admin page
- `admin_enqueue_scripts` — enqueue admin JS/CSS on plugin pages only
- `wp_enqueue_scripts` — conditionally enqueue app assets on pages using the shortcode
- `init` — register shortcode and Gutenberg block
- `wp_ajax_banzaiembed_upload_app` — handle zip upload via AJAX
- `wp_ajax_banzaiembed_delete_app` — handle app deletion

### Compatibility

- **WordPress:** 6.0+
- **PHP:** 7.4+
- **Supported build tools:** Vite, webpack, Parcel, Rollup, esbuild, or any tool that produces static JS/CSS output
- **Supported frameworks:** Vue 3, React 18+, or any framework/vanilla JS that mounts to a DOM element

## Development Priorities

1. Admin page with app list and add-new form (no upload yet — just the UI)
2. Zip upload and extraction to the correct directory
3. Asset detection (parse index.html → manifest → filename patterns → manual fallback)
4. Shortcode rendering (mount div + asset enqueuing)
5. Test with a real Vue 3 Vite build and a real React Vite build
6. Gutenberg block
7. App update/replace flow
8. Cache busting
9. Pro feature gating infrastructure (same pattern as BanzaiStyle)
10. Data Bridge (Pro)
11. Multi-page routing (Pro)

## Implementation Decisions (read before changing code)

The plugin is built. Where it departs from the spec above, it does so deliberately — [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) has the reasoning. In short:

- **Naming** follows BanzaiStyle's real convention, not `banzaiembed_` everywhere: namespace `BanzaiEmbed\`, functions `bzem_`, constants `BZEM_`, hooks `bzem/…`. Class files are `includes/class-{name}.php` via the autoloader.
- **Default mount ID** is the one in the build's index.html (`app` / `root`), not `bzem-{slug}` — that is what unmodified app code targets.
- **Cache-busting** is a new directory per upload (previous build kept for cached pages), not a `?ver=` query — a query string makes ES modules load twice.
- **Scripts** are printed as `type="module"` (Vite) or `defer` (classic), detected per build.
- **Managing apps** needs `manage_options` + `unfiltered_html`. Only allowlisted static file types are ever written from a zip.
- **Forms** post to admin-post.php, not AJAX.
- **Not built:** the "use WP's bundled React" option (a build-time decision the plugin can't make). Freemius is not initialised (no product ID yet); `BZEM_SIMULATE_PRO` unlocks pro for development.
- **Pro built:** Data Bridge and Environment Variables, as one card (`Data_Bridge`). Page data and env are inlined; user data is fetched by the app via `cfg.user()` from an uncached endpoint, because inlining it leaks it through page caches. "Custom PHP snippets" were dropped (eval) — the `bzem/app_data` / `bzem/user_data` filters replace them.
- **Pro built:** per-app custom CSS/JS (`Custom_Code`). "After" JS waits for the app to render into its mount element — an inline script after a module tag would run before it.
- **Pro not built yet:** multi-page routing.
