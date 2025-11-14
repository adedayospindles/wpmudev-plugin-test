import { createInterpolateElement } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

const ErrorBoundary = ({ children }) => {
	try {
		return children;
	} catch (err) {
		console.error("DriveApp error boundary caught:", err);
		return (
			<div className="sui-box sui-box-error">
				<div className="sui-box-header">
					<h2 className="sui-box-title">
						{__("Something went wrong", "wpmudev-plugin-test")}
					</h2>
				</div>
				<div className="sui-box-body">
					<p>
						{__(
							"An unexpected error occurred in the Google Drive interface. Please refresh the page or try again later.",
							"wpmudev-plugin-test"
						)}
					</p>
				</div>
			</div>
		);
	}
};

export default ErrorBoundary;
