/**
 * The "Details" panel in the block editor's document sidebar.
 *
 * Draws one control per field in the schema PHP handed over as
 * window.cozmicCoreFields, and writes straight to post meta. Adding a field is
 * an edit to cozmic_core_field_schema() in inc/meta.php; nothing here needs to
 * know what the fields are.
 *
 * Written against the wp.* globals rather than as a JSX module on purpose:
 * neither this plugin nor the theme has a build step, and a compile pipeline is
 * a poor trade for one sidebar panel. The cost is createElement calls instead of
 * markup, which for a flat list of controls is a fair price.
 *
 * @package CozmicCore
 */

( function ( wp ) {
	'use strict';

	var config = window.cozmicCoreFields;
	if ( ! config || ! config.fields || ! wp || ! wp.plugins || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;

	/*
	 * PluginDocumentSettingPanel moved from @wordpress/edit-post to
	 * @wordpress/editor in WordPress 6.6. The floor is 6.7 so wp.editor is the
	 * expected home, but the old location is still checked: a site pinned a
	 * version back should lose the panel gracefully rather than throw and take
	 * the whole editor down with it.
	 */
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;

	/**
	 * Map a schema field type to a control.
	 *
	 * datetime-local is a native browser control rather than core's date picker
	 * popover. It is one tap on a phone, it matches the value shape stored in
	 * meta exactly, and it needs no extra dependency.
	 */
	function controlFor( key, field, value, onChange ) {
		var shared = {
			key: key,
			label: field.label || key,
			help: field.help || undefined,
			value: value || '',
			onChange: onChange,
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		};

		if ( 'textarea' === field.type ) {
			return el( TextareaControl, shared );
		}

		if ( 'datetime' === field.type ) {
			shared.type = 'datetime-local';
		} else if ( 'url' === field.type ) {
			shared.type = 'url';
		}

		return el( TextControl, shared );
	}

	function CozmicFieldsPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		var controls = Object.keys( config.fields ).map( function ( key ) {
			return controlFor( key, config.fields[ key ], meta[ key ], function ( value ) {
				var next = {};
				next[ key ] = value;
				editPost( { meta: next } );
			} );
		} );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'cozmic-core-fields',
				title: config.title || 'Details',
				className: 'cozmic-core-fields',
			},
			controls
		);
	}

	registerPlugin( 'cozmic-core-fields', { render: CozmicFieldsPanel } );
} )( window.wp );
