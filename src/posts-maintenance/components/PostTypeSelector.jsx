import { __ } from "@wordpress/i18n";

/**
 * PostTypeSelector Component
 *
 * Renders a dropdown (or multi-select) for selecting post types.
 *
 * @param {Array} postTypes - List of available post types [{ name, label }].
 * @param {Function} onChange - Callback invoked when selection changes.
 * @param {boolean} multiple - Whether multiple selection is allowed (default: true).
 */
const PostTypeSelector = ({ postTypes, onChange, multiple = true }) => {
	/* ---------------- Handle missing or invalid post types ---------------- */
	if (!Array.isArray(postTypes) || postTypes.length === 0) {
		return (
			<p style={{ color: "red", marginTop: "1em" }}>
				{__(
					"No post types available. Please check your plugin configuration.",
					"wpmudev-plugin-test"
				)}
			</p>
		);
	}

	/* ---------------- Render dropdown ---------------- */
	return (
		<select
			multiple={multiple}
			className="pm-select"
			onChange={onChange}
			style={{ minWidth: "200px", padding: "0.4em" }}
		>
			{postTypes.map((pt) => (
				<option key={pt.name} value={pt.name}>
					{pt.label}
				</option>
			))}
		</select>
	);
};

export default PostTypeSelector;
