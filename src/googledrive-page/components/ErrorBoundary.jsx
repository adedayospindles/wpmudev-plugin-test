import { createInterpolateElement } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

/**
 * ErrorBoundary component
 * Catches rendering errors in child components and displays a fallback UI.
 *
 * Note: This is a simple functional try/catch boundary. For full React error boundaries
 * consider using a class-based component with componentDidCatch.
 */
const ErrorBoundary = ({ children }) => {
	try {
		// Attempt to render children
		return children;
	} catch (err) {
		// Log error to console for debugging
		console.error("DriveApp error boundary caught:", err);

		// Display fallback UI
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
