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

BanzaiEmbed is free and open source. Every feature below is included, with no limits on apps or pages and no licence key. It is distributed on GitHub: https://github.com/JoshRobs/Banzai-Embed

**What you get**

* **Upload a zip, get a shortcode.** `[banzai-embed app="my-app"]` — or the BanzaiEmbed App block in the block editor.
* **Entry files found for you.** BanzaiEmbed reads your build's `index.html` (or Vite's manifest, or Create React App's `asset-manifest.json`) to load exactly what your build tool intended, hashed filenames and all. If it can't tell, you pick.
* **Your app mounts unmodified.** The mount element gets the same ID your `index.html` used — `#app` for Vite + Vue, `#root` for Vite + React — so the code you already have finds it.
* **Loads only where it's used.** Scripts and styles are enqueued only on pages that embed the app, as ES modules for Vite builds and deferred scripts for webpack builds.
* **Several apps per page.** Each gets its own mount point, and repeated instances get unique IDs.
* **Isolated display for apps built to run on their own.** Show an app in the page, or give it its own page in a frame. Isolated, its CSS can't restyle your theme and your theme's can't restyle it, and a router written for the site root (`/play/…`) works without changes.
* **Switch apps on and off.** Turn an app off from the app list and it disappears from every page it is embedded on — no need to edit those pages — until you turn it back on.
* **Help where you need it.** A ? on every setting explains when to use it, and the Help page starts from the problem you're seeing — "my app changed my site's fonts", "refreshing shows Page not found" — and points to the fix.
* **Painless updates.** Upload a new build and every URL changes, so no browser or CDN can serve stale files. The previous build is kept, so pages cached before the update keep working.

**Images and fonts just work**

Your files are served from `wp-content/uploads/banzaiembed/…`, not the site root. If your build was made for the site root (Vite's default) or for another path, BanzaiEmbed points its image, font and other asset references — and Vite's lazy-loaded chunks — at the new location when you upload it. Files your app asks for by a path it builds while running, like `/sounds/${name}.mp3`, are sent to the right place when they're requested.

If BanzaiEmbed can't relocate an app's lazy-loaded chunks, it will tell you; build with a relative base instead:

* Vite: `base: './'` in `vite.config.js`
* Create React App: `"homepage": "."` in `package.json`
* webpack 5: `output.publicPath: 'auto'`

**What you'd otherwise write a custom plugin for**

* **Data Bridge.** Give your app WordPress data with no PHP. Choose values in the app's settings — the current post's ID, title, URL or custom fields, the site's name or REST API address, or your own text — and your app reads them from `window.banzaiEmbed['my-app'].data`. A React calculator on a property listing can start from that listing's price; one build works on every page.
* **Logged-in user data, safe with page caching.** Your app calls `cfg.user()` to get the current visitor's ID, display name, email or roles, and a REST API nonce for making authenticated requests. It's fetched fresh for each visitor, never written into the page, so a page cache can't show one visitor's details to another.
* **Environment variables.** Set values like API URLs per app, with separate values for staging and production. Your app reads them from `cfg.env`, so the same build runs on both.
* **Custom CSS & JS.** Add CSS that loads with the app, and JavaScript that runs before it starts or once it has rendered — for sizing, configuration, event listeners or analytics — without rebuilding it.
* **Site-wide placement.** Show an app on every page with no shortcode — chat widgets, feedback buttons, announcement bars — limited to the post types, pages and visitors you choose.
* **API proxy.** Built for Netlify or Vercel, your app calls its backend as `fetch('/api/…')`. Forward those paths to wherever the backend runs — a serverless function or your own API — and the app works on WordPress unchanged, with no CORS to set up. Visitors' cookies and WordPress logins are never passed on.
* **Client-side routing.** Using React Router or Vue Router? Put your app on a page such as `/portal/`, and links, bookmarks and refreshes on `/portal/settings` or `/portal/orders/42` load your app instead of a "not found" page. Your router gets the base path from `cfg.basePath` — or, for an Isolated app, nothing changes at all and the page's address follows the app's router. Real child pages of `/portal/` keep working.

== Installation ==

1. Download `banzaiembed-{version}.zip` from https://github.com/JoshRobs/Banzai-Embed/releases/latest
2. Go to **Plugins → Add New Plugin → Upload Plugin**, choose the zip, and activate BanzaiEmbed.
3. Go to **BanzaiEmbed → Add New**, name your app and upload a zip of your build output.
4. Copy the shortcode into any page, or add the **BanzaiEmbed App** block.

To update, download the new release and upload it the same way. WordPress offers to replace the installed version, and your apps are kept.

== Frequently Asked Questions ==

= Is it really free? =

Yes. Every feature is included, with no licence key, no account and no limits. It's GPL software, published on GitHub.

= Will WordPress update it automatically? =

No. BanzaiEmbed isn't listed on WordPress.org, so WordPress doesn't check it for updates. Watch the GitHub repository's releases, and upload a new zip when you want to update.

= Who can upload apps? =

Administrators who also have the `unfiltered_html` capability. An app is JavaScript that runs for every visitor, so on multisite only network administrators can upload one.

= What file types can a build contain? =

Static web assets only — JavaScript, CSS, HTML, JSON, images, fonts, media, source maps and WebAssembly. Anything else, including any PHP file, is left out of the upload and listed so you can see what was skipped.

= Can I put the same app on a page twice? =

Yes. The second copy gets a suffixed ID (`app-2`). Your entry script runs once, so to mount every copy, loop over `window.banzaiEmbed['my-app'].mounts`.

= Does it render my app in the block editor? =

Not in this version — the editor shows a placeholder, and the app runs on the published page.

= My app uses React Router or Vue Router. Why do refreshes show "Page not found"? =

WordPress doesn't know about your app's routes, so `/portal/settings` looks like a page that doesn't exist. Turn on **Routing** for the app and choose the page it's on; every path below that page then loads your app. Pass `cfg.basePath` to your router (`basename` in React Router, `createWebHistory()` in Vue Router), and give it a catch-all "not found" route of its own. Routing needs pretty permalinks.

= My app's styles change my whole site, or my theme breaks my app. =

Set the app's **Display** to **Isolated**. The app then gets its own page inside a frame, so styles on `body`, `h1` or `p` stay inside it, and your theme's styles stay out. The frame grows and shrinks with the app's content, or you can make it fill the window or give it a fixed height.

= My app uses a router built for the site root. Do I need to change it? =

Not when the app is Isolated: inside its frame, the app's address starts at the site root, as it would on its own host. With **Routing** turned on as well, the page's address follows the app — `/play/x` in the app is `/games/play/x` on the page — so links, bookmarks, refreshes and the back button all work.

= My app calls /api/… and gets a 404 on WordPress. =

That path was answered by your old host — a Netlify or Vercel function, or a dev-server proxy. Add an **API proxy** rule to the app: `/api` → `https://your-site.netlify.app/api`. Requests to `/api/…` on your WordPress site are then forwarded there, and the answers passed back. A Netlify `_redirects` line like `/api/judge /.netlify/functions/judge 200` becomes the rule `/api/judge` → `https://your-site.netlify.app/.netlify/functions/judge`.

= Can my app read data about the logged-in user? =

Yes — and only the fields you switch on for that app. Each visitor only ever receives their own details, fetched when your app asks for them, so they never end up in a cached page.

== External services ==

BanzaiEmbed itself loads nothing from other sites: your app's files are served from your own `wp-content/uploads` folder. Any services your own app calls are up to your app.

With the **API proxy**, your site forwards the requests your app makes to the paths you choose on to the URLs you enter, with the request's body, content type, Accept and Authorization headers, your app's own `X-` headers, and the visitor's IP address (as `X-Forwarded-For`). Nothing is forwarded until you add a rule, and cookies and WordPress logins never are.

The plugin collects no usage data and does not contact any service of its own.

== Changelog ==

= 1.0.0 =
* Initial release.
