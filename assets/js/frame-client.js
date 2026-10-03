/**
 * BanzaiEmbed — runs inside a framed app's document, before the app.
 *
 * Keeps the frame marker (?bzem_frame=slug.post) on every URL the app's
 * router pushes, so a reload inside the frame loads the app again, and tells
 * the page around the frame where the app has navigated and how tall it is.
 * Messages go to this site's own origin only.
 */
(function () {
	"use strict";

	var KEY = "bzem_frame";
	var meta = document.querySelector('meta[name="bzem-frame"]');
	var marker = new URLSearchParams(location.search).get(KEY) || (meta && meta.content);

	if (!marker || window.parent === window) {
		return;
	}

	function post(message) {
		message.bzem = marker;
		window.parent.postMessage(message, location.origin);
	}

	/**
	 * A same-origin URL with the marker added, as a path; anything else as given.
	 */
	function withMarker(url) {
		var parsed;

		try {
			parsed = new URL(String(url), location.href);
		} catch (e) {
			return url;
		}

		if (parsed.origin !== location.origin) {
			return url;
		}

		if (!parsed.searchParams.has(KEY)) {
			parsed.searchParams.set(KEY, marker);
		}

		return parsed.pathname + parsed.search + parsed.hash;
	}

	/**
	 * Where the app is, without the marker: what the page shows in its address bar.
	 */
	function route() {
		var url = new URL(location.href);

		url.searchParams.delete(KEY);
		post({ type: "route", path: url.pathname + url.search + url.hash });
	}

	// Loaded by a navigation that dropped the marker (a plain link): put it
	// back before the app's router reads the address.
	if (!new URLSearchParams(location.search).has(KEY)) {
		history.replaceState(history.state, "", withMarker(location.href));
	}

	["pushState", "replaceState"].forEach(function (name) {
		var original = history[name];

		history[name] = function (state, title, url) {
			var result = original.call(history, state, title, url === undefined || url === null ? url : withMarker(url));

			route();

			return result;
		};
	});

	window.addEventListener("popstate", route);
	window.addEventListener("hashchange", route);

	/*
	 * Height. Not body's own height: apps built to fill a page give body
	 * min-height: 100vh, which inside a frame is the frame's height, so it
	 * could only ever grow. The bottom of body's last child is the content.
	 */
	var last = 0;
	var growth = [];
	var stopped = false;

	function measure() {
		var body = document.body;
		var bottom = 0;
		var i;

		if (!body) {
			return 0;
		}

		for (i = 0; i < body.children.length; i++) {
			var child = body.children[i];
			var style = getComputedStyle(child);

			if (style.display === "none" || style.position === "fixed" || child.tagName === "SCRIPT") {
				continue;
			}

			bottom = Math.max(bottom, child.getBoundingClientRect().bottom + (parseFloat(style.marginBottom) || 0));
		}

		var bodyStyle = getComputedStyle(body);

		bottom += (parseFloat(bodyStyle.paddingBottom) || 0) + (parseFloat(bodyStyle.borderBottomWidth) || 0) + (parseFloat(bodyStyle.marginBottom) || 0);

		return Math.ceil(bottom + window.scrollY);
	}

	function size() {
		var height = measure();
		var now = Date.now();

		if (stopped || !height || Math.abs(height - last) < 2) {
			return;
		}

		// Content sized from the frame's own height (100vh plus a margin)
		// grows every time the frame does. Stop following it.
		if (height > last) {
			growth = growth.filter(function (t) {
				return now - t < 2000;
			});
			growth.push(now);

			if (growth.length > 12) {
				stopped = true;
				return;
			}
		}

		last = height;
		post({ type: "size", height: height });
	}

	function start() {
		if (window.ResizeObserver) {
			var observer = new ResizeObserver(size);

			observer.observe(document.documentElement);
			observer.observe(document.body);
		}

		// Children that grow without resizing body (absolutely positioned
		// ones, say) are caught on the next tick.
		setInterval(size, 1000);
		size();
		route();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", start);
	} else {
		start();
	}

	window.addEventListener("load", size);

	// The page's script asks again if it missed the first reports.
	window.addEventListener("message", function (event) {
		if (event.source === window.parent && event.origin === location.origin && event.data && event.data.bzemHello) {
			last = 0;
			size();
			route();
		}
	});
})();
