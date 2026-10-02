=== BanzaiEmbed — Vue & React Apps ===
Contributors: joshuaroberts
Tags: vue, react, shortcode, block, javascript
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
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
* **Painless updates.** Upload a new build and every URL changes, so no browser or CDN can serve stale files. The previous build is kept, so pages cached before the update keep working.

**Images and fonts just work**

Your files are served from `wp-content/uploads/banzaiembed/…`, not the site root. If your build was made for the site root (Vite's default) or for another path, BanzaiEmbed points its image, font and other asset references at the new location when you upload it.

For apps with lazy-loaded chunks, build with a relative base — BanzaiEmbed will tell you if yours needs it:

* Vite: `base: './'` in `vite.config.js`
* Create React App: `"homepage": "."` in `package.json`
* webpack 5: `output.publicPath: 'auto'`

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

== Changelog ==

= 0.1.0 =
* First development release.
