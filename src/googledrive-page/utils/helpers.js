export const fetchJson = async (url, opts = {}) => {
	opts.headers = opts.headers || {};
	opts.headers["X-WP-Nonce"] = window.wpmudevDriveTest.nonce;
	const res = await fetch(url, opts);
	if (!res.ok) {
		const txt = await res.text().catch(() => null);
		throw new Error(txt || `${res.status} ${res.statusText}`);
	}
	return res.json();
};

export function humanSize(bytes) {
	if (!bytes && bytes !== 0) return "—";
	const units = ["B", "KB", "MB", "GB", "TB"];
	let i = 0;
	let n = Number(bytes);
	while (n >= 1024 && i < units.length - 1) {
		n /= 1024;
		i++;
	}
	return `${Math.round(n * 10) / 10} ${units[i]}`;
}
