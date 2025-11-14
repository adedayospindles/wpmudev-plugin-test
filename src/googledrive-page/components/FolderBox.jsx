import { useState } from "@wordpress/element";
import { Button, TextControl, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { fetchJson } from "../utils/helpers";

const apiBase = "/wp-json/";

/**
 * FolderBox
 * Allows users to create a new folder in Google Drive via the plugin API.
 *
 * Props:
 * - showNotice: function to show success/error messages
 * - loadFiles: function to reload the file list after creation
 */
const FolderBox = ({ showNotice, loadFiles }) => {
	const [folderName, setFolderName] = useState("");
	const [isLoading, setIsLoading] = useState(false);

	const handleCreateFolder = async () => {
		const trimmedName = folderName.trim();
		if (!trimmedName) return;

		setIsLoading(true);
		try {
			const res = await fetchJson(
				apiBase + window.wpmudevDriveTest.restEndpointCreate,
				{
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ name: trimmedName }),
				}
			);

			if (res?.success) {
				setFolderName("");
				showNotice(
					__("Folder created successfully.", "wpmudev-plugin-test"),
					"success"
				);
				loadFiles();
			} else throw new Error(res?.message || "Create failed");
		} catch (e) {
			showNotice(
				`${__("Create folder error:", "wpmudev-plugin-test")} ${e.message}`,
				"error"
			);
		} finally {
			setIsLoading(false);
		}
	};

	return (
		<div className="sui-box">
			<div className="sui-box-header">
				<h2 className="sui-box-title">
					{__("Create New Folder", "wpmudev-plugin-test")}
				</h2>
			</div>
			<div className="sui-box-body">
				<TextControl
					label={__("Folder Name", "wpmudev-plugin-test")}
					value={folderName}
					onChange={setFolderName}
					placeholder={__("Enter folder name", "wpmudev-plugin-test")}
				/>
			</div>
			<div className="sui-box-footer">
				<div className="sui-actions-right">
					<Button
						variant="secondary"
						onClick={handleCreateFolder}
						disabled={isLoading || !folderName.trim()}
					>
						{isLoading ? (
							<Spinner />
						) : (
							__("Create Folder", "wpmudev-plugin-test")
						)}
					</Button>
				</div>
			</div>
		</div>
	);
};

export default FolderBox;
