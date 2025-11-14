export const fetchScanHistory = async (
	nonce,
	ajaxUrl,
	setHistory,
	setIsLoading
) => {
	setIsLoading(true);
	try {
		const formData = new FormData();
		formData.append("action", "wpmudev_get_scan_log");
		formData.append("nonce", nonce);

		const res = await fetch(ajaxUrl, {
			method: "POST",
			body: formData,
		});

		const json = await res.json();
		if (json.success) {
			setHistory(json.data.log);
		}
	} catch (e) {
		console.error("Failed to fetch scan history:", e);
	} finally {
		setIsLoading(false);
	}
};
