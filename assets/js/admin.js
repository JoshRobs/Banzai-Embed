/**
 * BanzaiEmbed admin screens: slug generation, copy buttons, delete
 * confirmation, the upload drop zone, the entry-file picker and the
 * active/inactive switches.
 *
 * Every form works without this file; it only makes them nicer.
 */
(function () {
	"use strict";

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/** Mirror App_Manager::sanitize_slug() closely enough to preview it. */
	function slugify(text) {
		return text
			.toLowerCase()
			.normalize("NFD")
			.replace(/[̀-ͯ]/g, "")
			.replace(/[^a-z0-9]+/g, "-")
			.replace(/^-+|-+$/g, "")
			.slice(0, 60);
	}

	function initSlug() {
		var name = document.getElementById("bzem-name");
		var slug = document.getElementById("bzem-slug");
		var preview = document.getElementById("bzem-slug-preview");

		if (!name || !slug) {
			return;
		}

		// Follow the name until the user types a slug of their own.
		var touched = slug.value !== "";

		function showPreview() {
			if (preview) {
				preview.textContent = '[banzai-embed app="' + (slugify(slug.value) || "my-app") + '"]';
			}
		}

		slug.addEventListener("input", function () {
			touched = slug.value !== "";
			showPreview();
		});

		name.addEventListener("input", function () {
			if (!touched) {
				slug.value = slugify(name.value);
				showPreview();
			}
		});
	}

	function copy(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}

		// Plain-http admin: the async clipboard API is unavailable.
		var area = document.createElement("textarea");
		area.value = text;
		area.setAttribute("readonly", "");
		area.style.position = "fixed";
		area.style.opacity = "0";
		document.body.appendChild(area);
		area.select();

		try {
			document.execCommand("copy");
		} finally {
			document.body.removeChild(area);
		}

		return Promise.resolve();
	}

	function initCopy() {
		document.addEventListener("click", function (event) {
			var button = event.target.closest(".bzem-copy");

			if (!button) {
				return;
			}

			copy(button.getAttribute("data-copy")).then(function () {
				// Icon buttons swap their icon for a tick; text buttons their text.
				var icon = button.querySelector(".dashicons");
				var label = icon ? button.getAttribute("aria-label") : button.textContent;
				var done = __("Copied!", "banzaiembed");

				if (icon) {
					icon.classList.replace("dashicons-admin-page", "dashicons-yes");
					button.classList.add("is-copied");
					button.setAttribute("aria-label", done);
				} else {
					button.textContent = done;
				}

				setTimeout(function () {
					if (icon) {
						icon.classList.replace("dashicons-yes", "dashicons-admin-page");
						button.classList.remove("is-copied");
						button.setAttribute("aria-label", label);
					} else {
						button.textContent = label;
					}
				}, 1500);
			});
		});
	}

	function initDelete() {
		document.addEventListener("click", function (event) {
			var link = event.target.closest(".bzem-delete");

			if (!link) {
				return;
			}

			var message = sprintf(
				/* translators: %s: app name. */
				__('Delete "%s" and all of its files? Pages that embed it will show nothing.', "banzaiembed"),
				link.getAttribute("data-name")
			);

			if (!window.confirm(message)) {
				event.preventDefault();
			}
		});
	}

	function initDropzone() {
		var zone = document.querySelector(".bzem-dropzone");

		if (!zone) {
			return;
		}

		var input = zone.querySelector("input[type=file]");
		var label = zone.querySelector(".bzem-dropzone-file");

		function show() {
			var file = input.files && input.files[0];
			label.textContent = file ? file.name : "";
			zone.classList.toggle("has-file", !!file);
		}

		input.addEventListener("change", show);

		["dragenter", "dragover"].forEach(function (type) {
			zone.addEventListener(type, function (event) {
				event.preventDefault();
				zone.classList.add("is-dragging");
			});
		});

		["dragleave", "drop"].forEach(function (type) {
			zone.addEventListener(type, function () {
				zone.classList.remove("is-dragging");
			});
		});

		zone.addEventListener("drop", function (event) {
			event.preventDefault();

			if (event.dataTransfer && event.dataTransfer.files.length) {
				input.files = event.dataTransfer.files;
				show();
			}
		});
	}

	function initEntryPicker() {
		document.addEventListener("click", function (event) {
			var button = event.target.closest(".bzem-add-entry");

			if (!button) {
				return;
			}

			var area = document.getElementById(button.getAttribute("data-target"));
			var path = button.getAttribute("data-path");
			var lines = area.value.split(/\r?\n/).filter(Boolean);

			if (lines.indexOf(path) === -1) {
				lines.push(path);
				area.value = lines.join("\n");
			}

			area.focus();
		});
	}

	/**
	 * Data Bridge and API proxy rows: add (cloning the section's <template>),
	 * remove, and show the value field only for sources that take one.
	 */
	function initBridge() {
		// Row indexes only need to be unique within one submit.
		var next = Date.now();

		document.querySelectorAll(".bzem-bridge, .bzem-proxy").forEach(function (card) {
			initRows(card);
		});

		function initRows(card) {
			function syncValue(select) {
				var option = select.options[select.selectedIndex];
				var input = select.closest("tr").querySelector(".bzem-bridge-value");
				var placeholder = option && option.getAttribute("data-placeholder");

				input.hidden = !placeholder;

				if (placeholder) {
					input.placeholder = placeholder;
				}
			}

			card.addEventListener("change", function (event) {
				if (event.target.matches(".bzem-bridge-source")) {
					syncValue(event.target);
				}
			});

			card.addEventListener("click", function (event) {
				var add = event.target.closest(".bzem-bridge-add");
				var remove = event.target.closest(".bzem-bridge-remove");

				if (add) {
					// The add button's <p> follows the section's <template>.
					var template = add.parentNode.previousElementSibling;
					var rows = template.previousElementSibling.querySelector(".bzem-bridge-rows");
					var html = template.innerHTML.replace(/__i__/g, String(next++));

					rows.insertAdjacentHTML("beforeend", html);
					rows.lastElementChild.querySelector("input").focus();
				} else if (remove) {
					var row = remove.closest("tr");
					var body = row.parentNode;

					// Keep one row to type into; clearing it saves as "no rows".
					if (body.children.length > 1) {
						body.removeChild(row);
					} else {
						row.querySelectorAll("input").forEach(function (input) {
							input.value = "";
						});
					}
				}
			});
		}
	}

	/**
	 * Custom CSS & JS: WordPress's CodeMirror, when the server enqueued it
	 * (it does not if the user turned syntax highlighting off). CodeMirror
	 * copies itself back into the textarea when the form submits.
	 */
	function initCodeEditors() {
		var settings = window.bzemCodeEditor;

		if (!settings || !wp.codeEditor) {
			return;
		}

		// :disabled covers a card locked for want of a licence — CodeMirror
		// would otherwise make it editable again.
		document.querySelectorAll(".bzem-code-editor:not(:disabled)").forEach(function (area) {
			wp.codeEditor.initialize(area, settings[area.getAttribute("data-mode")]);
		});
	}

	/**
	 * Placement (Pro): show the rules only for site-wide apps, the post type
	 * list only when limiting to post types, and filter long page lists.
	 * Without this everything is simply visible.
	 */
	function initPlacement() {
		var rules = document.querySelector("[data-bzem-rules]");

		if (!rules) {
			return;
		}

		var form = rules.closest("form");
		var types = rules.querySelector("[data-bzem-scope-types]");

		function checked(name) {
			var input = form.querySelector('input[name="' + name + '"]:checked');
			return input ? input.value : "";
		}

		function sync() {
			rules.hidden = checked("placement") !== "site_wide";

			if (types) {
				types.hidden = checked("rules_scope") !== "post_types";
			}
		}

		form.addEventListener("change", function (event) {
			if (event.target.name === "placement" || event.target.name === "rules_scope") {
				sync();
			}
		});
		sync();

		var filter = rules.querySelector(".bzem-page-filter");
		var pages = rules.querySelector("[data-bzem-pages]");

		if (!filter || !pages) {
			return;
		}

		filter.hidden = false;

		filter.addEventListener("input", function () {
			var query = filter.value.trim().toLowerCase();

			pages.querySelectorAll("label").forEach(function (label) {
				label.hidden = query !== "" && label.textContent.toLowerCase().indexOf(query) === -1;
			});
		});

		// Enter in the filter would otherwise submit the whole form.
		filter.addEventListener("keydown", function (event) {
			if (event.key === "Enter") {
				event.preventDefault();
			}
		});
	}

	/**
	 * Routing (Pro): show the pages and usage only while routing is on, and
	 * filter long page lists. Without this everything is simply visible.
	 */
	/**
	 * Display: show the frame's options only while Isolated is chosen.
	 */
	function initDisplay() {
		var options = document.querySelector("[data-bzem-frame-options]");
		var radios = document.querySelectorAll("[data-bzem-display]");

		if (!options || !radios.length) {
			return;
		}

		function sync() {
			var framed = document.querySelector("[data-bzem-display][value=frame]");

			options.hidden = !framed.checked;
		}

		radios.forEach(function (radio) {
			radio.addEventListener("change", sync);
		});
		sync();

		// Typing a height means "fixed".
		var px = options.querySelector("[name=frame_px]");
		var fixed = options.querySelector("[name=frame_height][value=fixed]");

		px.addEventListener("input", function () {
			fixed.checked = true;
		});
	}

	function initRouting() {
		var toggle = document.querySelector("[data-bzem-routing-toggle]");
		var details = document.querySelector("[data-bzem-routing-details]");

		if (!toggle || !details) {
			return;
		}

		function sync() {
			details.hidden = !toggle.checked;
		}

		toggle.addEventListener("change", sync);
		sync();

		var filter = details.querySelector(".bzem-page-filter");
		var pages = details.querySelector("[data-bzem-routing-pages]");

		if (!filter || !pages) {
			return;
		}

		filter.hidden = false;

		filter.addEventListener("input", function () {
			var query = filter.value.trim().toLowerCase();

			pages.querySelectorAll("label").forEach(function (label) {
				label.hidden = query !== "" && label.textContent.toLowerCase().indexOf(query) === -1;
			});
		});

		// Enter in the filter would otherwise submit the whole form.
		filter.addEventListener("keydown", function (event) {
			if (event.key === "Enter") {
				event.preventDefault();
			}
		});
	}

	/**
	 * Pro: the "Activate Licence" menu item links here with ?bzem_activate=1.
	 * Open Freemius's dialog through the header button, once — the param is
	 * dropped so a refresh doesn't reopen it. Freemius binds the button's
	 * click handler in a footer script, so wait for the page to finish.
	 */
	function initLicence() {
		var params = new URLSearchParams(window.location.search);

		if (!params.has("bzem_activate")) {
			return;
		}

		params.delete("bzem_activate");
		window.history.replaceState(null, "", window.location.pathname + (params.toString() ? "?" + params : "") + window.location.hash);

		window.addEventListener("load", function () {
			var button = document.querySelector("[data-bzem-activate]");

			if (button) {
				button.click();
			}
		});
	}

	/**
	 * The list's on/off switches post the same form without leaving the
	 * page. The switch flips straight away and flips back if saving fails.
	 */
	function initToggles() {
		if (!window.fetch || !window.FormData) {
			return;
		}

		function setState(form, active) {
			var button = form.querySelector(".bzem-switch");
			var row = form.closest("tr");

			button.setAttribute("aria-checked", active ? "true" : "false");
			button.setAttribute("title", active ? __("Deactivate", "banzaiembed") : __("Activate", "banzaiembed"));
			form.querySelector("input[name=active]").value = active ? "0" : "1";

			if (row) {
				row.classList.toggle("is-inactive", !active);
			}

			// Keep the Active / Inactive counts honest.
			[["active", active ? 1 : -1], ["inactive", active ? -1 : 1]].forEach(function (pair) {
				var count = document.querySelector('[data-bzem-count="' + pair[0] + '"]');

				if (count) {
					count.textContent = String(Math.max(0, (parseInt(count.textContent.replace(/\D/g, ""), 10) || 0) + pair[1]));
				}
			});
		}

		document.addEventListener("submit", function (event) {
			var form = event.target.closest(".bzem-toggle-form");

			if (!form) {
				return;
			}

			event.preventDefault();

			var button = form.querySelector(".bzem-switch");

			if (button.getAttribute("aria-busy") === "true") {
				return;
			}

			var active = form.querySelector("input[name=active]").value === "1";
			var body = new FormData(form);

			body.append("ajax", "1");
			setState(form, active);
			button.setAttribute("aria-busy", "true");

			// getAttribute: the form's <input name="action"> shadows form.action.
			fetch(form.getAttribute("action"), { method: "POST", body: body, credentials: "same-origin" })
				.then(function (response) {
					return response.json().then(function (json) {
						if (!response.ok || !json.success) {
							// The handler's own explanation, when it sent one.
							throw { message: json && typeof json.data === "string" ? json.data : "" };
						}
					});
				})
				.catch(function (error) {
					// Anything else — a network error, an HTML error page — gets the generic message.
					var message = error instanceof Error ? "" : error.message;

					setState(form, !active);
					window.alert(message || __("The app could not be updated. Reload the page and try again.", "banzaiembed"));
				})
				.then(function () {
					button.removeAttribute("aria-busy");
				});
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		initSlug();
		initCodeEditors();
		initCopy();
		initDelete();
		initDropzone();
		initEntryPicker();
		initToggles();
		initPlacement();
		initDisplay();
		initRouting();
		initLicence();
		initBridge();
	});
})();
