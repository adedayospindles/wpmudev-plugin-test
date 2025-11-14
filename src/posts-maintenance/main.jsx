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

// Import modular components for better separation of concerns
import PostTypeSelector from "./components/PostTypeSelector";
import PostList from "./components/PostList";
import ScanHistory from "./components/ScanHistory";

// Root DOM element where React mounts
const domElement = document.getElementById("wpmudev-posts-maintenance-root");

const PostsMaintenanceApp = () => {
	// Track loading state during AJAX calls
	const [isLoading, setIsLoading] = useState(false);

	// Display success or error notifications
	const [notice, setNotice] = useState(null);

	// Hold selected post type and post IDs
	const [selectedType, setSelectedType] = useState("");
	const [selectedPostIds, setSelectedPostIds] = useState([]);

	/**
	 * Helper: Display transient notice message for 5 seconds
	 */
	const showNotice = (message, status = "success") => {
		setNotice({ message, status });
		setTimeout(() => setNotice(null), 5000);
	};

	/**
	 * Handle the post scan action.
	 * Submits AJAX request to the backend handler (wpmudev_scan_posts).
	 */
	const handleScan = async () => {
		setIsLoading(true);
		try {
			const formData = new FormData();
			formData.append("action", "wpmudev_scan_posts");
			formData.append("nonce", window.WPMUDEV_PM.nonce);

			// Send selected post IDs if available, otherwise use post type
			if (selectedPostIds.length > 0) {
				selectedPostIds.forEach((id) => formData.append("post_ids[]", id));
			} else if (selectedType) {
				formData.append("post_types[]", selectedType);
			}

			// Perform AJAX request to WordPress admin-ajax.php
			const res = await fetch(window.WPMUDEV_PM.ajax_url, {
				method: "POST",
				body: formData,
			});

			const json = await res.json();

			// Handle success or error based on JSON response
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

	return (
		<div className="sui-box">
			{/* Header Section */}
			<div className="sui-box-header">
				<h2 className="sui-box-title">
					{__("Posts Maintenance", "wpmudev-plugin-test")}
				</h2>
			</div>

			{/* Main Body Section */}
			<div className="sui-box-body">
				{/* Display active notice */}
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

				{/* Post type selection dropdown */}
				<PostTypeSelector
					postTypes={window.WPMUDEV_PM.postTypes}
					onChange={(e) => {
						const type = Array.from(e.target.selectedOptions)[0]?.value;
						setSelectedType(type);
						setSelectedPostIds([]); // Reset post selection when type changes
					}}
				/>

				{/* Conditionally render PostList once a type is selected */}
				{selectedType && (
					<PostList
						selectedType={selectedType}
						onSelect={(ids) => setSelectedPostIds(ids)}
					/>
				)}

				{/* Display scan log history */}
				<ScanHistory />
			</div>

			{/* Footer with scan button */}
			<div className="sui-box-footer">
				<Button
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

/* ---------------- Bootstrap ----------------
 * Mount React app using React 18's createRoot if available.
 * Fallback to render() for older WordPress setups.
 */
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
