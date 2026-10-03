/**
 * BanzaiEmbed admin screens: slug generation, copy buttons, delete
 * confirmation, the upload drop zone and the entry-file picker.
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

		if (!name || !slug) {
			return;
		}

		// Follow the name until the user types a slug of their own.
		var touched = slug.value !== "";

		slug.addEventListener("input", function () {
			touched = slug.value !== "";
		});

		name.addEventListener("input", function () {
			if (!touched) {
				slug.value = slugify(name.value);
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

			var label = button.textContent;

			copy(button.getAttribute("data-copy")).then(function () {
				button.textContent = __("Copied!", "banzaiembed");
				setTimeout(function () {
					button.textContent = label;
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
	 * Data Bridge rows: add (cloning the section's <template>), remove, and
	 * show the value field only for sources that take one.
	 */
	function initBridge() {
		var card = document.querySelector(".bzem-bridge");

		if (!card) {
			return;
		}

		// Row indexes only need to be unique within one submit.
		var next = Date.now();

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

		document.querySelectorAll(".bzem-code-editor").forEach(function (area) {
			wp.codeEditor.initialize(area, settings[area.getAttribute("data-mode")]);
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		initSlug();
		initCodeEditors();
		initCopy();
		initDelete();
		initDropzone();
		initEntryPicker();
		initBridge();
	});
})();
