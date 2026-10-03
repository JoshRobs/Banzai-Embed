/**
 * The BanzaiEmbed App block, editor side.
 *
 * Plain ES5 against the wp.* globals — there is no build step. The front end
 * is rendered in PHP (see Block::render), so save() returns null.
 */
(function (wp) {
	"use strict";

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var blockEditor = wp.blockEditor;
	var components = wp.components;
	var data = window.bzemBlockData || { apps: [], adminUrl: "" };

	function findApp(slug) {
		for (var i = 0; i < data.apps.length; i++) {
			if (data.apps[i].slug === slug) {
				return data.apps[i];
			}
		}

		return null;
	}

	function appOptions() {
		var options = [{ value: "", label: __("Select an app…", "banzaiembed") }];

		data.apps.forEach(function (app) {
			var label = app.name;

			if (app.status !== "ready") {
				label = sprintf("%s (%s)", app.name, app.statusLabel);
			} else if (!app.active) {
				label = sprintf("%s (%s)", app.name, __("Inactive", "banzaiembed"));
			}

			options.push({ value: app.slug, label: label });
		});

		return options;
	}

	function Edit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var app = findApp(attributes.app);
		var blockProps = blockEditor.useBlockProps({ className: "bzem-block" });

		var picker = el(components.SelectControl, {
			label: __("App", "banzaiembed"),
			value: attributes.app,
			options: appOptions(),
			onChange: function (value) {
				setAttributes({ app: value });
			},
		});

		var inspector = el(
			blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __("App settings", "banzaiembed") },
				picker,
				el(components.TextControl, {
					label: __("Mount element ID", "banzaiembed"),
					help: app
						? sprintf(__("Leave blank to use #%s.", "banzaiembed"), app.mountId)
						: __("Leave blank to use the app's own.", "banzaiembed"),
					value: attributes.mountId,
					onChange: function (value) {
						setAttributes({ mountId: value.replace(/[^A-Za-z0-9_-]/g, "") });
					},
				}),
				el(components.TextControl, {
					label: __("Inline styles", "banzaiembed"),
					help: __("CSS for the container, e.g. min-height: 400px;", "banzaiembed"),
					value: attributes.inlineStyle,
					onChange: function (value) {
						setAttributes({ inlineStyle: value });
					},
				})
			)
		);

		var body;

		if (!data.apps.length) {
			body = el(
				"p",
				null,
				__("No apps uploaded yet.", "banzaiembed"),
				data.adminUrl ? " " : null,
				data.adminUrl ? el("a", { href: data.adminUrl, target: "_blank", rel: "noopener" }, __("Add one in BanzaiEmbed", "banzaiembed")) : null
			);
		} else if (!app) {
			body = picker;
		} else {
			body = el(
				"div",
				{ className: "bzem-block-summary" },
				el("strong", null, app.name),
				el("span", null, app.framework + " · #" + (attributes.mountId || app.mountId)),
				app.status !== "ready" ? el("span", { className: "bzem-block-warning" }, app.statusLabel) : null,
				app.status === "ready" && !app.active
					? el("span", { className: "bzem-block-warning" }, __("Inactive — visitors see nothing until it is activated.", "banzaiembed"))
					: null,
				el("span", { className: "bzem-block-note" }, __("The app runs on the published page; the editor shows this placeholder.", "banzaiembed"))
			);
		}

		return el(
			"div",
			blockProps,
			inspector,
			el(
				components.Placeholder,
				{ icon: "layout", label: __("BanzaiEmbed App", "banzaiembed") },
				body
			)
		);
	}

	wp.blocks.registerBlockType("banzaiembed/app", {
		edit: Edit,
		save: function () {
			return null;
		},
	});
})(window.wp);
