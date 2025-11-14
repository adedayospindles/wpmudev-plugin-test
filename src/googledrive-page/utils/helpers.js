/**
 * Fetch JSON data from a given URL with WordPress nonce included.
 * @param {string} url - The endpoint URL.
 * @param {Object} opts - Fetch options (optional).
 * @returns {Promise<Object>} - Parsed JSON response.
 * @throws {Error} - Throws if response is not ok.
 */
export const fetchJson = async (url, opts = {}) => {
	// Ensure headers object exists
	opts.headers = opts.headers || {};

	// Attach WordPress nonce for authentication
	opts.headers["X-WP-Nonce"] = window.wpmudevDriveTest.nonce;

	const res = await fetch(url, opts);

	// Throw an error if response is not ok
	if (!res.ok) {
		const txt = await res.text().catch(() => null);
		throw new Error(txt || `${res.status} ${res.statusText}`);
	}

	return res.json();
};

/**
 * Convert bytes into a human-readable format.
 * @param {number} bytes - Size in bytes.
 * @returns {string} - Human-readable size (e.g., "2.3 MB").
 */
export function humanSize(bytes) {
	if (!bytes && bytes !== 0) return "—"; // Handle null/undefined

	const units = ["B", "KB", "MB", "GB", "TB"];
	let i = 0;
	let n = Number(bytes);

	// Convert bytes to largest fitting unit
	while (n >= 1024 && i < units.length - 1) {
		n /= 1024;
		i++;
	}

	// Round to 1 decimal place
	return `${Math.round(n * 10) / 10} ${units[i]}`;
}
