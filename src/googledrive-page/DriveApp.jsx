import { useState } from "@wordpress/element";
import ErrorBoundary from "./components/ErrorBoundary";
import NoticeMessage from "./components/NoticeMessage";
import AuthBox from "./components/AuthBox";
import UploadBox from "./components/UploadBox";
import FolderBox from "./components/FolderBox";
import FileList from "./components/FileList";

const DriveApp = () => {
	// ----------------------------
	// State Management
	// ----------------------------
	const [notice, setNotice] = useState(null); // Current notice message
	const [hasCredentials, setHasCredentials] = useState(
		Boolean(window.wpmudevDriveTest.hasCredentials)
	);
	const [refreshTrigger, setRefreshTrigger] = useState(0); // shared trigger for FileList reload

	// ----------------------------
	// Helper Functions
	// ----------------------------
	/**
	 * Show a temporary notice message
	 * @param {string} message - The notice message
	 * @param {string} status - The notice status ('success', 'error', etc.)
	 */
	const showNotice = (message, status = "success") => {
		setNotice({ message, status });
		setTimeout(() => setNotice(null), 7000); // Auto-dismiss after 7s
	};

	/**
	 * Trigger a refresh of the FileList
	 */
	const triggerRefresh = () => {
		setRefreshTrigger((prev) => prev + 1);
	};

	// ----------------------------
	// Render
	// ----------------------------
	return (
		<ErrorBoundary>
			{/* Header */}
			<div className="sui-header">
				<h1 className="sui-header-title">Google Drive Test</h1>
				<p className="sui-description">
					Test Google Drive API integration for applicant assessment
				</p>
			</div>

			{/* Notice Message */}
			<NoticeMessage notice={notice} onDismiss={() => setNotice(null)} />

			{/* Authentication Box */}
			<AuthBox showNotice={showNotice} setHasCredentials={setHasCredentials} />

			{/* Conditionally render Google Drive controls if authenticated */}
			{hasCredentials && (
				<>
					{/* Upload File Box */}
					<UploadBox
						showNotice={showNotice}
						onUploadComplete={triggerRefresh}
					/>

					{/* Create Folder Box */}
					<FolderBox showNotice={showNotice} onFolderCreated={triggerRefresh} />

					{/* File List */}
					<FileList showNotice={showNotice} refreshTrigger={refreshTrigger} />
				</>
			)}
		</ErrorBoundary>
	);
};

export default DriveApp;
