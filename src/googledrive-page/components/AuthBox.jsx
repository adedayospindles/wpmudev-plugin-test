import { useState, useEffect } from "@wordpress/element";
import { Button, TextControl, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { createInterpolateElement } from "@wordpress/element";
import { fetchJson } from "../utils/helpers";

// const apiBase = "/wp-json/";

/**
 * AuthBox component for managing Google Drive authentication.
 * - Handles saving credentials (Client ID / Secret)
 * - Initiates OAuth flow
 * - Shows notices for success or error
 */
const AuthBox = ({ showNotice, setHasCredentials }) => {
	// ----------------------------
	// Component state
	// ----------------------------
	const [credentials, setCredentials] = useState({
		clientId: "",
		clientSecret: "",
	});
	const [showCredentials, setShowCredentials] = useState(
		!window.wpmudevDriveTest.hasCredentials
	);
	const [isAuthenticated, setIsAuthenticated] = useState(
		Boolean(window.wpmudevDriveTest.authStatus)
	);
	const [isLoading, setIsLoading] = useState(false);

	// ----------------------------
	// Initialize credentials from global config
	// ----------------------------
	useEffect(() => {
		if (window.wpmudevDriveTest.credentials) {
			setCredentials(window.wpmudevDriveTest.credentials);
		}
	}, []);

	// ----------------------------
	// Save credentials to server
	// ----------------------------
	const handleSaveCredentials = async () => {
		setIsLoading(true);
		try {
			const body = {
				client_id: credentials.clientId,
				client_secret: credentials.clientSecret,
			};

			const res = await fetchJson(
				//apiBase + window.wpmudevDriveTest.restEndpointSave,
				window.wpmudevDriveTest.restBase +
					window.wpmudevDriveTest.restEndpointSave,
				{
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify(body),
				}
			);

			if (res && res.success !== false) {
				setHasCredentials(true);
				setShowCredentials(false);
				showNotice(
					__(
						"Credentials saved. You can now authenticate.",
						"wpmudev-plugin-test"
					),
					"success"
				);
			} else {
				throw new Error(res.message || "Unknown error");
			}
		} catch (e) {
			showNotice(
				`${__("Failed to save credentials:", "wpmudev-plugin-test")} ${
					e.message
				}`,
				"error"
			);
		} finally {
			setIsLoading(false);
		}
	};

	// ----------------------------
	// Trigger Google OAuth authentication
	// ----------------------------
	const handleAuth = async () => {
		setIsLoading(true);
		try {
			const res = await fetchJson(
				//apiBase + window.wpmudevDriveTest.restEndpointAuth,
				window.wpmudevDriveTest.restBase +
					window.wpmudevDriveTest.restEndpointAuth,
				{ method: "POST" }
			);

			if (res?.auth_url) {
				window.location.href = res.auth_url;
				return;
			}
			throw new Error(res?.message || "Missing auth_url");
		} catch (e) {
			showNotice(
				`${__("Authentication failed:", "wpmudev-plugin-test")} ${e.message}`,
				"error"
			);
		} finally {
			setIsLoading(false);
		}
	};

	// ----------------------------
	// Render: Credentials Input Form
	// ----------------------------
	if (showCredentials) {
		return (
			<div className="sui-box">
				<div className="sui-box-header">
					<h2 className="sui-box-title">
						{__("Set Google Drive Credentials", "wpmudev-plugin-test")}
					</h2>
				</div>

				<div className="sui-box-body">
					<TextControl
						label={__("Client ID", "wpmudev-plugin-test")}
						value={credentials.clientId}
						onChange={(v) => setCredentials({ ...credentials, clientId: v })}
						help={createInterpolateElement(
							__(
								"You can get Client ID from <a>Google Cloud Console</a>. Make sure to enable Google Drive API.",
								"wpmudev-plugin-test"
							),
							{
								a: (
									<a
										href="https://console.cloud.google.com/apis/credentials"
										target="_blank"
										rel="noopener noreferrer"
									/>
								),
							}
						)}
					/>

					<TextControl
						label={__("Client Secret", "wpmudev-plugin-test")}
						type="password"
						value={credentials.clientSecret}
						onChange={(v) =>
							setCredentials({ ...credentials, clientSecret: v })
						}
						help={createInterpolateElement(
							__(
								"You can get Client Secret from <a>Google Cloud Console</a>.",
								"wpmudev-plugin-test"
							),
							{
								a: (
									<a
										href="https://console.cloud.google.com/apis/credentials"
										target="_blank"
										rel="noopener noreferrer"
									/>
								),
							}
						)}
					/>

					<p>
						<strong>
							{__(
								"Required scopes for Google Drive API:",
								"wpmudev-plugin-test"
							)}
						</strong>
					</p>
					<ul>
						<li>https://www.googleapis.com/auth/drive.file</li>
						<li>https://www.googleapis.com/auth/drive.readonly</li>
					</ul>
				</div>

				<div className="sui-box-footer">
					<div className="sui-actions-right">
						<Button
							variant="primary"
							onClick={handleSaveCredentials}
							disabled={isLoading}
						>
							{isLoading ? (
								<Spinner />
							) : (
								__("Save Credentials", "wpmudev-plugin-test")
							)}
						</Button>
					</div>
				</div>
			</div>
		);
	}

	// ----------------------------
	// Render: Authentication Button
	// ----------------------------
	if (!isAuthenticated) {
		return (
			<div className="sui-box">
				<div className="sui-box-header">
					<h2 className="sui-box-title">
						{__("Authenticate with Google Drive", "wpmudev-plugin-test")}
					</h2>
				</div>

				<div className="sui-box-body">
					<p>
						{__(
							"Please authenticate with Google Drive to proceed with the test.",
							"wpmudev-plugin-test"
						)}
					</p>
				</div>

				<div className="sui-box-footer">
					<div className="sui-actions-left">
						<Button
							variant="secondary"
							onClick={() => setShowCredentials(true)}
						>
							{__("Change Credentials", "wpmudev-plugin-test")}
						</Button>
					</div>
					<div className="sui-actions-right">
						<Button variant="primary" onClick={handleAuth} disabled={isLoading}>
							{isLoading ? (
								<Spinner />
							) : (
								__("Authenticate with Google Drive", "wpmudev-plugin-test")
							)}
						</Button>
					</div>
				</div>
			</div>
		);
	}

	// ----------------------------
	// Nothing to render if already authenticated
	// ----------------------------
	return null;
};

export default AuthBox;
