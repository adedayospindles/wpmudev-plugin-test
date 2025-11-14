import { useState } from "@wordpress/element";
import ErrorBoundary from "./components/ErrorBoundary";
import NoticeMessage from "./components/NoticeMessage";
import AuthBox from "./components/AuthBox";
import UploadBox from "./components/UploadBox";
import FolderBox from "./components/FolderBox";
import FileList from "./components/FileList";

const DriveApp = () => {
	const [notice, setNotice] = useState(null);
	const [hasCredentials, setHasCredentials] = useState(
		Boolean(window.wpmudevDriveTest.hasCredentials)
	);

	const showNotice = (message, status = "success") => {
		setNotice({ message, status });
		setTimeout(() => setNotice(null), 7000);
	};

	return (
		<ErrorBoundary>
			<div className="sui-header">
				<h1 className="sui-header-title">Google Drive Test</h1>
				<p className="sui-description">
					Test Google Drive API integration for applicant assessment
				</p>
			</div>

			<NoticeMessage notice={notice} onDismiss={() => setNotice(null)} />

			<AuthBox showNotice={showNotice} setHasCredentials={setHasCredentials} />

			{hasCredentials && (
				<>
					<UploadBox
						showNotice={showNotice}
						loadFiles={() => document.dispatchEvent(new Event("loadFiles"))}
					/>
					<FolderBox
						showNotice={showNotice}
						loadFiles={() => document.dispatchEvent(new Event("loadFiles"))}
					/>
					<FileList showNotice={showNotice} />
				</>
			)}
		</ErrorBoundary>
	);
};

export default DriveApp;
