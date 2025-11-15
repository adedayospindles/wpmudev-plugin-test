/**
 * Posts Maintenance Admin React UI
 *
 * Handles post scanning, background scheduling, and scan history display.
 * Communicates securely with the backend via AJAX (admin-ajax.php).
 */

import { createRoot, render, StrictMode, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button, Spinner, Notice } from "@wordpress/components";
import "./scss/style.scss";

// Modular component imports
import PostTypeSelector from "./components/PostTypeSelector";
import PostList from "./components/PostList";
import ScanHistory from "./components/ScanHistory";

// Root DOM element where React mounts
const domElement = document.getElementById("wpmudev-posts-maintenance-root");

const PostsMaintenanceApp = () => {
	/* ---------------- State Management ---------------- */
	// Loading state during AJAX requests
	const [isLoading, setIsLoading] = useState(false);

	// Display success/error messages
	const [notice, setNotice] = useState(null);

	// Selected post type
	const [selectedType, setSelectedType] = useState("");

	// Selected post IDs
	const [selectedPostIds, setSelectedPostIds] = useState([]);

	/* ---------------- Helper Functions ---------------- */
	/**
	 * Show a transient notice for 5 seconds
	 * @param {string} message
	 * @param {string} status - 'success' or 'error'
	 */
	const showNotice = (message, status = "success") => {
		setNotice({ message, status });
		setTimeout(() => setNotice(null), 5000);
	};

	/**
	 * Handle scanning posts
	 * Sends AJAX request to backend (wpmudev_scan_posts)
	 */
	const handleScan = async () => {
		setIsLoading(true);
		try {
			const formData = new FormData();
			formData.append("action", "wpmudev_scan_posts");
			formData.append("nonce", window.WPMUDEV_PM.nonce);

			// Include selected post IDs if any; otherwise, post type
			if (selectedPostIds.length > 0) {
				selectedPostIds.forEach((id) => formData.append("post_ids[]", id));
			} else if (selectedType) {
				formData.append("post_types[]", selectedType);
			}

			// Perform AJAX request
			const res = await fetch(window.WPMUDEV_PM.ajax_url, {
				method: "POST",
				body: formData,
			});
			const json = await res.json();

			// Handle response
			if (json.success) {
				showNotice(json.data.message, "success");
			} else {
				throw new Error(json.data?.message || "Scan failed");
			}
		} catch (e) {
			showNotice(
				`${__("Error:", "wpmudev-plugin-test")} ${e.message}`,
				"error"
			);
		} finally {
			setIsLoading(false);
		}
	};

	/* ---------------- Render ---------------- */
	return (
		<div className="sui-box">
			{/* Header */}
			<div className="sui-box-header">
				<h2 className="sui-box-title">
					{__("Posts Maintenance", "wpmudev-plugin-test")}
				</h2>
			</div>

			{/* Body */}
			<div className="sui-box-body">
				{/* Active notice */}
				{notice && (
					<Notice
						status={notice.status === "error" ? "error" : "success"}
						isDismissible
						onRemove={() => setNotice(null)}
					>
						{notice.message}
					</Notice>
				)}

				{/* Instructions */}
				<p>
					{__(
						"Select a post type and choose specific posts to scan.",
						"wpmudev-plugin-test"
					)}
				</p>

				{/* Post type selector */}
				<PostTypeSelector
					postTypes={window.WPMUDEV_PM.postTypes}
					onChange={(e) => {
						const type = Array.from(e.target.selectedOptions)[0]?.value;
						setSelectedType(type);
						setSelectedPostIds([]); // Reset posts when type changes
					}}
				/>

				{/* Post list for selected type */}
				{selectedType && (
					<PostList
						selectedType={selectedType}
						onSelect={(ids) => setSelectedPostIds(ids)}
					/>
				)}

				{/* Scan history */}
				<ScanHistory />
			</div>

			{/* Footer with Scan button */}
			<div className="sui-box-footer">
				<Button
					className="btn-primary pm-scan-button"
					variant="primary"
					onClick={handleScan}
					disabled={
						isLoading || (!selectedType && selectedPostIds.length === 0)
					}
				>
					{isLoading ? <Spinner /> : __("Scan Posts", "wpmudev-plugin-test")}
				</Button>
			</div>
		</div>
	);
};

/* ---------------- Bootstrap ---------------- */
// Use React 18 createRoot if available; fallback to render for older setups
if (createRoot) {
	createRoot(domElement).render(
		<StrictMode>
			<PostsMaintenanceApp />
		</StrictMode>
	);
} else {
	render(
		<StrictMode>
			<PostsMaintenanceApp />
		</StrictMode>,
		domElement
	);
}
