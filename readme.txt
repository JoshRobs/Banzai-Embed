=== BanzaiEmbed — Vue & React App Embedder ===
Contributors: joshuaroberts
Tags: vue, react, shortcode, block, javascript
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed pre-built Vue, React or vanilla JavaScript apps in any page or post. Upload your build as a zip — no Node.js on the server.

== Description ==

You built a calculator, a configurator, a dashboard or a booking widget in Vue or React. Now it needs to live on a WordPress page.

BanzaiEmbed takes the build you already have — the contents of `dist/` or `build/` — as a zip, works out which files boot it, and gives you a shortcode and a block to put it anywhere. Nothing is compiled on the server, so it works on any host.

**What you get**

* **Upload a zip, get a shortcode.** `[banzai-embed app="my-app"]` — or the BanzaiEmbed App block in the block editor.
* **Entry files found for you.** BanzaiEmbed reads your build's `index.html` (or Vite's manifest, or Create React App's `asset-manifest.json`) to load exactly what your build tool intended, hashed filenames and all. If it can't tell, you pick.
* **Your app mounts unmodified.** The mount element gets the same ID your `index.html` used — `#app` for Vite + Vue, `#root` for Vite + React — so the code you already have finds it.
* **Loads only where it's used.** Scripts and styles are enqueued only on pages that embed the app, as ES modules for Vite builds and deferred scripts for webpack builds.
* **Several apps per page.** Each gets its own mount point, and repeated instances get unique IDs.
* **Switch apps on and off.** Turn an app off from the app list and it disappears from every page it is embedded on — no need to edit those pages — until you turn it back on.
* **Painless updates.** Upload a new build and every URL changes, so no browser or CDN can serve stale files. The previous build is kept, so pages cached before the update keep working.

**Images and fonts just work**

Your files are served from `wp-content/uploads/banzaiembed/…`, not the site root. If your build was made for the site root (Vite's default) or for another path, BanzaiEmbed points its image, font and other asset references at the new location when you upload it.

For apps with lazy-loaded chunks, build with a relative base — BanzaiEmbed will tell you if yours needs it:

* Vite: `base: './'` in `vite.config.js`
* Create React App: `"homepage": "."` in `package.json`
* webpack 5: `output.publicPath: 'auto'`

**BanzaiEmbed Pro**

Everything above is free, with no limits on apps or pages. Pro adds what you'd otherwise write a custom plugin for:

* **Data Bridge.** Give your app WordPress data with no PHP. Choose values in the app's settings — the current post's ID, title, URL or custom fields, the site's name or REST API address, or your own text — and your app reads them from `window.banzaiEmbed['my-app'].data`. A React calculator on a property listing can start from that listing's price; one build works on every page.
* **Logged-in user data, safe with page caching.** Your app calls `cfg.user()` to get the current visitor's ID, display name, email or roles, and a REST API nonce for making authenticated requests. It's fetched fresh for each visitor, never written into the page, so a page cache can't show one visitor's details to another.
* **Environment variables.** Set values like API URLs per app, with separate values for staging and production. Your app reads them from `cfg.env`, so the same build runs on both.
* **Custom CSS & JS.** Add CSS that loads with the app, and JavaScript that runs before it starts or once it has rendered — for sizing, configuration, event listeners or analytics — without rebuilding it.
* **Site-wide placement.** Show an app on every page with no shortcode — chat widgets, feedback buttons, announcement bars — limited to the post types, pages and visitors you choose.
* **Client-side routing.** Using React Router or Vue Router? Put your app on a page such as `/portal/`, and links, bookmarks and refreshes on `/portal/settings` or `/portal/orders/42` load your app instead of a "not found" page. Your router gets the base path from `cfg.basePath`. Real child pages of `/portal/` keep working.

Upgrade from **BanzaiEmbed → Upgrade** in your dashboard.

== Installation ==

1. Install and activate BanzaiEmbed.
2. Go to **BanzaiEmbed → Add New**, name your app and upload a zip of your build output.
3. Copy the shortcode into any page, or add the **BanzaiEmbed App** block.

== Frequently Asked Questions ==

= Who can upload apps? =

Administrators who also have the `unfiltered_html` capability. An app is JavaScript that runs for every visitor, so on multisite only network administrators can upload one.

= What file types can a build contain? =

Static web assets only — JavaScript, CSS, HTML, JSON, images, fonts, media, source maps and WebAssembly. Anything else, including any PHP file, is left out of the upload and listed so you can see what was skipped.

= Can I put the same app on a page twice? =

Yes. The second copy gets a suffixed ID (`app-2`). Your entry script runs once, so to mount every copy, loop over `window.banzaiEmbed['my-app'].mounts`.

= Does it render my app in the block editor? =

Not in this version — the editor shows a placeholder, and the app runs on the published page.

= What does Pro add? =

The Data Bridge (WordPress and logged-in user data for your app), environment variables, per-app custom CSS and JavaScript, site-wide placement, and client-side routing for apps using React Router or Vue Router. See **BanzaiEmbed Pro** above. Embedding, uploading and updating apps is free and stays free.

= What happens if my Pro licence expires? =

Nothing breaks. Apps keep receiving their Data Bridge data and environment variables, custom CSS and JavaScript keep loading, site-wide apps keep showing, and routed apps keep their routes. Changing those settings needs an active licence again.

= My app uses React Router or Vue Router. Why do refreshes show "Page not found"? =

WordPress doesn't know about your app's routes, so `/portal/settings` looks like a page that doesn't exist. With Pro, turn on **Routing** for the app and choose the page it's on; every path below that page then loads your app. Pass `cfg.basePath` to your router (`basename` in React Router, `createWebHistory()` in Vue Router), and give it a catch-all "not found" route of its own. Routing needs pretty permalinks.

= Can my app read data about the logged-in user? =

With Pro, yes — and only the fields you switch on for that app. Each visitor only ever receives their own details, fetched when your app asks for them, so they never end up in a cached page.

== Changelog ==

= 1.0.0 =
* Initial release.
