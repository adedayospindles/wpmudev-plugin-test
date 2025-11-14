import { useState, useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Spinner, SelectControl, Button } from "@wordpress/components";
import { fetchScanHistory } from "../utils/fetchScanHistory";

const ITEMS_PER_PAGE = 10; // Number of entries per page

const ScanHistory = () => {
	const [history, setHistory] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [filterType, setFilterType] = useState("");
	const [filterSource, setFilterSource] = useState("");
	const [currentPage, setCurrentPage] = useState(1);

	useEffect(() => {
		fetchScanHistory(
			window.WPMUDEV_PM.nonce,
			window.WPMUDEV_PM.ajax_url,
			setHistory,
			setIsLoading
		);
	}, []);

	// Filter history
	const filtered = history.filter((entry) => {
		return (
			(!filterType || entry.type === filterType) &&
			(!filterSource || entry.source === filterSource)
		);
	});

	// Pagination
	const totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE);
	const paginated = filtered.slice(
		(currentPage - 1) * ITEMS_PER_PAGE,
		currentPage * ITEMS_PER_PAGE
	);

	const goPrev = () => setCurrentPage((prev) => Math.max(prev - 1, 1));
	const goNext = () => setCurrentPage((prev) => Math.min(prev + 1, totalPages));
	const goToPage = (page) =>
		setCurrentPage(Math.min(Math.max(page, 1), totalPages));

	// Reset page when filter changes
	useEffect(() => {
		setCurrentPage(1);
	}, [filterType, filterSource]);

	return (
		<div className="pm-scan-history">
			<h4>{__("Scan History", "wpmudev-plugin-test")}</h4>

			<div style={{ display: "flex", gap: "1em", marginBottom: "1em" }}>
				<SelectControl
					label={__("Filter by Post Type", "wpmudev-plugin-test")}
					value={filterType}
					options={[
						{ label: __("All", "wpmudev-plugin-test"), value: "" },
						...window.WPMUDEV_PM.postTypes.map((pt) => ({
							label: pt.label,
							value: pt.name,
						})),
					]}
					onChange={(val) => setFilterType(val)}
				/>
				<SelectControl
					label={__("Filter by Source", "wpmudev-plugin-test")}
					value={filterSource}
					options={[
						{ label: __("All", "wpmudev-plugin-test"), value: "" },
						{ label: __("Manual", "wpmudev-plugin-test"), value: "manual" },
						{
							label: __("Scheduled", "wpmudev-plugin-test"),
							value: "scheduled",
						},
						{
							label: __("Background", "wpmudev-plugin-test"),
							value: "background",
						},
					]}
					onChange={(val) => setFilterSource(val)}
				/>
			</div>

			{isLoading ? (
				<Spinner />
			) : paginated.length > 0 ? (
				<>
					<table className="pm-history-table">
						<thead>
							<tr>
								<th>{__("Post ID", "wpmudev-plugin-test")}</th>
								<th>{__("Type", "wpmudev-plugin-test")}</th>
								<th>{__("Source", "wpmudev-plugin-test")}</th>
								<th>{__("Timestamp", "wpmudev-plugin-test")}</th>
							</tr>
						</thead>
						<tbody>
							{paginated.map((entry, index) => (
								<tr key={index}>
									<td>{entry.post_id}</td>
									<td>{entry.type}</td>
									<td>{entry.source}</td>
									<td>{entry.timestamp}</td>
								</tr>
							))}
						</tbody>
					</table>

					<div
						style={{
							marginTop: "1em",
							display: "flex",
							gap: "1em",
							alignItems: "center",
						}}
					>
						<Button isSecondary onClick={goPrev} disabled={currentPage === 1}>
							{__("Prev", "wpmudev-plugin-test")}
						</Button>
						<span>
							{__("Page", "wpmudev-plugin-test")} {currentPage} / {totalPages}
						</span>
						<Button
							isSecondary
							onClick={goNext}
							disabled={currentPage === totalPages}
						>
							{__("Next", "wpmudev-plugin-test")}
						</Button>
					</div>
				</>
			) : (
				<p>{__("No scan history found.", "wpmudev-plugin-test")}</p>
			)}
		</div>
	);
};

export default ScanHistory;
