/**
 * Fetch scan history via WordPress AJAX.
 *
 * @param {string} nonce - Security nonce.
 * @param {string} ajaxUrl - admin-ajax.php URL.
 * @param {Function} setHistory - State setter for scan history.
 * @param {Function} setIsLoading - State setter for loading status.
 */
export const fetchScanHistory = async (
	nonce,
	ajaxUrl,
	setHistory,
	setIsLoading
) => {
	/* ---------------- Set loading state ---------------- */
	setIsLoading(true);

	try {
		/* ---------------- Prepare FormData ---------------- */
		const formData = new FormData();
		formData.append("action", "wpmudev_get_scan_log");
		formData.append("nonce", nonce);

		/* ---------------- Perform AJAX Request ---------------- */
		const res = await fetch(ajaxUrl, { method: "POST", body: formData });
		const json = await res.json();

		/* ---------------- Handle Response ---------------- */
		if (json.success) {
			setHistory(json.data.log);
		}
	} catch (e) {
		/* ---------------- Error Handling ---------------- */
		console.error("Failed to fetch scan history:", e);
	} finally {
		/* ---------------- Reset loading state ---------------- */
		setIsLoading(false);
	}
};
