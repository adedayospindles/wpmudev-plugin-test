import { useState, useEffect } from "@wordpress/element";
import { Button, Spinner, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { fetchJson } from "../utils/helpers";

//const apiBase = "/wp-json/";
const apiBase = window.wpmudevDriveTest.restBase;

/**
 * FileList component
 * Displays Google Drive files, supports pagination, download, and deletion.
 */
const FileList = ({ showNotice, refreshTrigger }) => {
	// State management
	const [files, setFiles] = useState([]);
	const [pageToken, setPageToken] = useState(""); // Current page token
	const [nextPageToken, setNextPageToken] = useState(""); // Token for next page
	const [prevTokens, setPrevTokens] = useState([]); // Stack of previous tokens
	const [pageCache, setPageCache] = useState({}); // Cache for pages
	const [isLoadingFiles, setIsLoadingFiles] = useState(false);
	const [downloadingId, setDownloadingId] = useState(null);
	const [deletingId, setDeletingId] = useState(null);
	const [page, setPage] = useState(1);

	/**
	 * Load files from API or cache.
	 * @param {string} token
	 * @param {number} pageNum
	 * @param {boolean} force - bypass cache
	 */
	const loadFiles = async (token = "", pageNum = 1, force = false) => {
		// Use cached page if available, unless force reload
		if (!force && pageCache[token]) {
			setFiles(pageCache[token].files);
			setNextPageToken(pageCache[token].nextPageToken || "");
			setPageToken(token);
			setPage(pageNum);
			return;
		}

		setIsLoadingFiles(true);

		try {
			const res = await fetchJson(
				apiBase +
					window.wpmudevDriveTest.restEndpointFiles +
					`?per_page=12${token ? `&pageToken=${token}` : ""}`
			);

			if (res?.files) {
				setFiles(res.files);
				setNextPageToken(res.nextPageToken || "");
				setPageToken(token);
				setPage(pageNum);

				// Save to cache (limit 5 pages)
				setPageCache((prev) => {
					const newCache = {
						...prev,
						[token]: { files: res.files, nextPageToken: res.nextPageToken },
					};
					const keys = Object.keys(newCache);
					if (keys.length > 5) delete newCache[keys[0]]; // remove oldest
					return newCache;
				});
			} else {
				setFiles([]);
				setNextPageToken("");
			}
		} catch (e) {
			showNotice(
				`${__("Failed to load files:", "wpmudev-plugin-test")} ${e.message}`,
				"error"
			);
		} finally {
			setIsLoadingFiles(false);
		}
	};

	/**
	 * Pagination: Next page
	 */
	const handleNext = () => {
		if (nextPageToken) {
			setPrevTokens([...prevTokens, pageToken]); // Save current token
			loadFiles(nextPageToken, page + 1);
		}
	};

	/**
	 * Pagination: Previous page
	 */
	const handlePrev = () => {
		if (prevTokens.length > 0) {
			const tokensCopy = [...prevTokens];
			const prevToken = tokensCopy.pop();
			setPrevTokens(tokensCopy);
			loadFiles(prevToken, page - 1);
		}
	};

	/**
	 * Download a file
	 */
	const handleDownload = async (fileId, filename) => {
		setDownloadingId(fileId);
		try {
			const res = await fetchJson(
				apiBase +
					window.wpmudevDriveTest.restEndpointDownload +
					`?file_id=${encodeURIComponent(fileId)}`
			);

			if (res?.success && res.content) {
				const byteChars = atob(res.content);
				const byteNumbers = Array.from(byteChars).map((c) => c.charCodeAt(0));
				const blob = new Blob([new Uint8Array(byteNumbers)], {
					type: res.mimeType || "application/octet-stream",
				});
				const url = URL.createObjectURL(blob);

				const a = document.createElement("a");
				a.href = url;
				a.download = res.filename || filename || "download";
				document.body.appendChild(a);
				a.click();
				a.remove();
				URL.revokeObjectURL(url);

				showNotice(__("Download ready", "wpmudev-plugin-test"), "success");
			} else throw new Error(res?.message || "Download failed");
		} catch (e) {
			showNotice(
				`${__("Download error:", "wpmudev-plugin-test")} ${e.message}`,
				"error"
			);
		} finally {
			setDownloadingId(null);
		}
	};

	/**
	 * Delete a file
	 */
	const handleDelete = async (fileId, filename) => {
		if (
			!window.confirm(`${__("Delete", "wpmudev-plugin-test")} "${filename}"?`)
		)
			return;

		setDeletingId(fileId);

		try {
			const res = await fetchJson(
				apiBase +
					window.wpmudevDriveTest.restEndpointDelete +
					`?file_id=${encodeURIComponent(fileId)}`,
				{ method: "DELETE" }
			);

			if (res?.success) {
				// Optimistic update: remove file immediately from local state
				setFiles((prev) => prev.filter((f) => f.id !== fileId));

				showNotice(
					__("File deleted successfully", "wpmudev-plugin-test"),
					"success"
				);

				// Clear cache and force reload to ensure fresh data
				setPageCache({});
				setPrevTokens([]);
				setPage(1);
				await loadFiles(pageToken, page, true);
			} else throw new Error(res?.message || "Delete failed");
		} catch (e) {
			showNotice(
				`${__("Delete error:", "wpmudev-plugin-test")} ${e.message}`,
				"error"
			);
		} finally {
			setDeletingId(null);
		}
	};

	// Initial load on mount
	useEffect(() => {
		loadFiles("", 1);
	}, []);

	// Refresh files when `refreshTrigger` changes
	useEffect(() => {
		if (refreshTrigger) {
			setPageCache({});
			setPrevTokens([]);
			setPage(1);
			loadFiles("", 1, true); // force reload
		}
	}, [refreshTrigger]);

	/**
	 * Render
	 */
	return (
		<div className="sui-box">
			{/* Header */}
			<div className="sui-box-header">
				<h2 className="sui-box-title">
					{__("Your Drive Files", "wpmudev-plugin-test")}
				</h2>
				<div className="sui-actions-right">
					<Button
						variant="secondary"
						onClick={() => {
							setPageCache({});
							setPrevTokens([]);
							setPage(1);
							loadFiles("", 1, true);
						}}
						disabled={isLoadingFiles}
					>
						{isLoadingFiles ? (
							<Spinner />
						) : (
							__("Refresh Files", "wpmudev-plugin-test")
						)}
					</Button>
				</div>
			</div>

			{/* Body */}
			<div className="sui-box-body">
				{/* Info notice */}
				<Notice status="warning" isDismissible={false}>
					{__(
						"Only files created by this app can be deleted. Other files are read‑only due to Google Drive permissions.",
						"wpmudev-plugin-test"
					)}
				</Notice>

				{/* File grid or loading / empty state */}
				{files.length ? (
					<>
						<div className="drive-files-grid">
							{files.map((file) => (
								<div className="drive-file-item" key={file.id}>
									<div className="file-info">
										<strong>{file.name}</strong>
										<small>
											{file.size ? `${(file.size / 1024).toFixed(1)} KB` : "—"}{" "}
											•{" "}
											{file.modifiedTime
												? new Date(file.modifiedTime).toLocaleString()
												: "—"}
										</small>
									</div>

									<div className="file-actions">
										{file.mimeType !== "application/vnd.google-apps.folder" && (
											<Button
												className="btn-download"
												variant="secondary"
												onClick={() => handleDownload(file.id, file.name)}
												disabled={downloadingId === file.id}
											>
												{downloadingId === file.id ? (
													<Spinner />
												) : (
													__("Download", "wpmudev-plugin-test")
												)}
											</Button>
										)}

										<Button
											className="btn-delete"
											variant="secondary"
											isDestructive
											onClick={() => handleDelete(file.id, file.name)}
											disabled={deletingId === file.id || !file.canDelete}
											title={
												!file.canDelete
													? __(
															"This file cannot be deleted due to limited permissions",
															"wpmudev-plugin-test"
													  )
													: undefined
											}
										>
											{deletingId === file.id ? (
												<Spinner />
											) : (
												__("Delete", "wpmudev-plugin-test")
											)}
										</Button>

										{file.webViewLink && (
											<Button
												className="btn-view-in-drive"
												variant="link"
												size="small"
												href={file.webViewLink}
												target="_blank"
												rel="noopener noreferrer"
											>
												{__("View in Drive", "wpmudev-plugin-test")}
											</Button>
										)}
									</div>
								</div>
							))}
						</div>

						{/* Pagination */}
						<div className="drive-pagination">
							<Button
								className="btn-prev"
								variant="secondary"
								onClick={handlePrev}
								disabled={page <= 1 || isLoadingFiles}
							>
								{__("Previous", "wpmudev-plugin-test")}
							</Button>
							<Button
								className="btn-next"
								variant="secondary"
								onClick={handleNext}
								disabled={!nextPageToken || isLoadingFiles}
							>
								{__("Next", "wpmudev-plugin-test")}
							</Button>
							<span className="drive-pagination-page">
								{`${__("Page", "wpmudev-plugin-test")} ${page}`}
							</span>
						</div>
					</>
				) : isLoadingFiles ? (
					<div className="drive-loading">
						<Spinner />
						<p>{__("Loading files...", "wpmudev-plugin-test")}</p>
					</div>
				) : (
					<div className="sui-box-settings-row">
						<p>
							{__(
								"No files found. Upload one to get started.",
								"wpmudev-plugin-test"
							)}
						</p>
						<Button
							className="btn-upload"
							variant="primary"
							onClick={() =>
								document.querySelector(".drive-file-input")?.click()
							}
						>
							{__("Upload File", "wpmudev-plugin-test")}
						</Button>
					</div>
				)}
			</div>

			{/* Loading overlay for pagination */}
			{isLoadingFiles && files.length > 0 && (
				<div className="drive-loading-more">
					<Spinner />
					<p>{__("Loading more files...", "wpmudev-plugin-test")}</p>
				</div>
			)}
		</div>
	);
};

export default FileList;
