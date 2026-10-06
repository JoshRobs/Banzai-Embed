/**
 * BanzaiEmbed — runs on a page with a framed app.
 *
 * Sizes each frame to its content (when its height is "auto"), and, when
 * the app is routed, keeps the page's address in step with the app's
 * router: /play/x inside the frame is /games/play/x on the page, so links,
 * bookmarks and refreshes come back to the same screen.
 */
(function () {
	"use strict";

	function frames() {
		return Array.prototype.slice.call(document.querySelectorAll("iframe[data-bzem-frame]"));
	}

	function find(source) {
		var list = frames();

		for (var i = 0; i < list.length; i++) {
			if (list[i].contentWindow === source) {
				var config = {};

				try {
					config = JSON.parse(list[i].getAttribute("data-bzem-frame")) || {};
				} catch (e) {}

				return { el: list[i], config: config };
			}
		}

		return null;
	}

	/**
	 * A frame may have reported before this script ran: ask each to say
	 * again where it is and how tall.
	 */
	function hello() {
		frames().forEach(function (frame) {
			if (frame.contentWindow) {
				frame.contentWindow.postMessage({ bzemHello: true }, location.origin);
			}
		});
	}

	/**
	 * The page URL for a path inside the frame: the frame's root swapped
	 * for the page's base.
	 */
	function pageUrl(config, path) {
		var root = config.root || "/";

		if (path.indexOf(root) !== 0 && path + "/" !== root) {
			return null;
		}

		return config.base + "/" + path.slice(root.length);
	}

	window.addEventListener("message", function (event) {
		var data = event.data;

		if (event.origin !== location.origin || !data || !data.bzem) {
			return;
		}

		var frame = find(event.source);

		if (!frame) {
			return;
		}

		if (data.type === "size" && frame.config.height === "auto" && data.height > 0) {
			frame.el.style.height = data.height + "px";
		} else if (data.type === "route" && typeof frame.config.base === "string" && typeof data.path === "string") {
			var url = pageUrl(frame.config, data.path);

			// Replace, never push: the frame's own navigation already made
			// the history entry the back button returns to.
			if (url && url !== location.pathname + location.search + location.hash) {
				history.replaceState(history.state, "", url);
			}
		}
	});

	hello();
})();
