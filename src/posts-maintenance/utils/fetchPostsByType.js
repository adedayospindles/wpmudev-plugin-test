/**
 * Fetch posts by type via WordPress AJAX.
 *
 * @param {string} type - Post type to fetch.
 * @param {string} nonce - Security nonce.
 * @param {string} ajaxUrl - admin-ajax.php URL.
 * @param {Function} [setAvailablePosts] - Optional state setter callback.
 * @returns {Promise<Array>} - Resolves with an array of posts, empty if none or on error.
 */
export const fetchPostsByType = async (
	type,
	nonce,
	ajaxUrl,
	setAvailablePosts
) => {
	try {
		/* ---------------- Prepare FormData ---------------- */
		const formData = new FormData();
		formData.append("action", "wpmudev_get_posts_by_type");
		formData.append("nonce", nonce);
		formData.append("post_type", type);

		/* ---------------- Perform AJAX Request ---------------- */
		const res = await fetch(ajaxUrl, { method: "POST", body: formData });
		const json = await res.json();

		/* ---------------- Handle Response ---------------- */
		if (json.success && Array.isArray(json.data.posts)) {
			setAvailablePosts?.(json.data.posts);
			return json.data.posts;
		} else {
			console.warn("No posts returned or AJAX failed:", json.data?.message);
			return [];
		}
	} catch (error) {
		/* ---------------- Error Handling ---------------- */
		console.error("Failed to fetch posts:", error);
		return [];
	}
};
