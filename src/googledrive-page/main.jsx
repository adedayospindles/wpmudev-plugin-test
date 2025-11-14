import { createRoot, render, StrictMode } from "@wordpress/element";
import "./scss/style.scss";
import DriveApp from "./DriveApp";

const domElement = document.getElementById(
	window.wpmudevDriveTest.dom_element_id
);

if (domElement) {
	try {
		if (createRoot) {
			createRoot(domElement).render(
				<StrictMode>
					<DriveApp />
				</StrictMode>
			);
		} else {
			render(
				<StrictMode>
					<DriveApp />
				</StrictMode>,
				domElement
			);
		}
	} catch (err) {
		console.error("DriveApp critical render error:", err);
		domElement.innerHTML = `<div class="sui-notice sui-notice-error"><p>Failed to initialize Google Drive UI: ${err.message}</p></div>`;
	}
} else {
	console.error(
		"DriveApp: Root DOM element not found:",
		window.wpmudevDriveTest.dom_element_id
	);
}
