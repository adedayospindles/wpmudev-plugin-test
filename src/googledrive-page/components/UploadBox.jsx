import { useState, useRef } from "@wordpress/element";
import {
	Button,
	Spinner,
	ProgressBar,
	ToggleControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { humanSize } from "../utils/helpers";

const apiBase = "/wp-json/";

const UploadBox = ({ showNotice, loadFiles }) => {
	const inputRef = useRef(null);
	const [selectedFiles, setSelectedFiles] = useState([]);
	const [isUploading, setIsUploading] = useState(false);
	const [isDragOver, setIsDragOver] = useState(false);
	const [uploadMode, setUploadMode] = useState("sequential"); // "sequential" or "parallel"

	// user selected files
	const onFilesSelected = (files) => {
		const list = Array.from(files || []).map((f) => ({
			file: f,
			progress: 0,
			status: "pending",
			message: "",
		}));
		setSelectedFiles(list);
	};

	// upload one file
	const uploadSingle = (item, index) => {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			const fd = new FormData();
			fd.append("file", item.file);

			xhr.open("POST", apiBase + window.wpmudevDriveTest.restEndpointUpload);
			xhr.setRequestHeader("X-WP-Nonce", window.wpmudevDriveTest.nonce);

			xhr.upload.onprogress = (ev) => {
				if (ev.lengthComputable) {
					const pct = Math.round((ev.loaded / ev.total) * 100);
					setSelectedFiles((prev) => {
						const next = [...prev];
						if (next[index]) {
							next[index] = {
								...next[index],
								progress: pct,
								status: "uploading",
							};
						}
						return next;
					});
				}
			};

			xhr.onload = () => {
				if (xhr.status >= 200 && xhr.status < 300) {
					try {
						const json = JSON.parse(xhr.responseText);
						const resItem = Array.isArray(json?.files)
							? json.files[0]
							: json?.file ?? null;
						setSelectedFiles((prev) => {
							const next = [...prev];
							next[index] = {
								...next[index],
								progress: 100,
								status:
									resItem && (resItem.id || resItem.success) ? "done" : "error",
								message: resItem?.name
									? __("Uploaded", "wpmudev-plugin-test")
									: json?.message || "",
							};
							return next;
						});
						resolve(json);
					} catch (err) {
						setSelectedFiles((prev) => {
							const next = [...prev];
							next[index] = {
								...next[index],
								status: "error",
								message: err.message,
							};
							return next;
						});
						reject(err);
					}
				} else {
					const msg = xhr.statusText || "Upload failed";
					setSelectedFiles((prev) => {
						const next = [...prev];
						next[index] = { ...next[index], status: "error", message: msg };
						return next;
					});
					reject(new Error(msg));
				}
			};

			xhr.onerror = () => {
				setSelectedFiles((prev) => {
					const next = [...prev];
					next[index] = {
						...next[index],
						status: "error",
						message: "Network error",
					};
					return next;
				});
				reject(new Error("Network error during upload"));
			};

			xhr.send(fd);
		});
	};

	// unified upload handler
	const handleUpload = async () => {
		if (!selectedFiles.length) {
			showNotice(
				__("Please select file(s) to upload.", "wpmudev-plugin-test"),
				"error"
			);
			return;
		}
		setIsUploading(true);

		if (uploadMode === "sequential") {
			for (let i = 0; i < selectedFiles.length; i++) {
				try {
					await uploadSingle(selectedFiles[i], i);
				} catch (err) {
					// continue with next file
				}
			}
		} else {
			await Promise.all(
				selectedFiles.map((item, idx) =>
					uploadSingle(item, idx).catch(() => null)
				)
			);
		}

		setIsUploading(false);
		if (inputRef.current) inputRef.current.value = "";
		setPageCache({});
		setPrevTokens([]);
		setPage(1);
		loadFiles("", 1);
		//loadFiles(1);
		//setTimeout(() => loadFiles(1), 1500);
	};

	// clear selection
	const clearSelection = () => {
		if (inputRef.current) inputRef.current.value = "";
		setSelectedFiles([]);
	};

	// drag & drop handlers
	const onDragOver = (e) => {
		e.preventDefault();
		setIsDragOver(true);
	};
	const onDragLeave = () => setIsDragOver(false);
	const onDrop = (e) => {
		e.preventDefault();
		setIsDragOver(false);
		if (e.dataTransfer.files.length) {
			onFilesSelected(e.dataTransfer.files);
		}
	};

	return (
		<div className="sui-box">
			<div className="sui-box-header">
				<h2 className="sui-box-title">
					{__("Upload Files to Drive", "wpmudev-plugin-test")}
				</h2>
			</div>

			<div
				className={`sui-box-body drive-drop-zone ${
					isDragOver ? "drag-over" : ""
				}`}
				onDragOver={onDragOver}
				onDragLeave={onDragLeave}
				onDrop={onDrop}
			>
				<p>
					{__(
						"Drag & drop files here or use the picker below:",
						"wpmudev-plugin-test"
					)}
				</p>

				<input
					ref={inputRef}
					type="file"
					multiple
					disabled={isUploading}
					aria-label={__("Select file(s) to upload", "wpmudev-plugin-test")}
					onChange={(e) => onFilesSelected(e.target.files)}
					className="drive-file-input"
				/>

				{selectedFiles.length > 0 && (
					<div className="upload-file-list" style={{ marginTop: 10 }}>
						{selectedFiles.map((it, idx) => (
							<div key={idx} className="upload-file-row">
								<strong>{it.file.name}</strong>
								<div>
									<small>
										{humanSize(it.file.size)} — {it.status}
									</small>
									{it.status === "uploading" && (
										<ProgressBar
											value={it.progress}
											label={`${it.progress}%`}
										/>
									)}
									{it.status === "error" && (
										<div className="upload-error">{it.message}</div>
									)}
								</div>
							</div>
						))}
					</div>
				)}
			</div>

			<div className="sui-box-footer">
				<div className="sui-actions-left">
					<Button
						variant="secondary"
						onClick={clearSelection}
						disabled={isUploading || !selectedFiles.length}
					>
						{__("Clear", "wpmudev-plugin-test")}
					</Button>
					<ToggleControl
						label={__("Upload Mode", "wpmudev-plugin-test")}
						help={
							uploadMode === "sequential"
								? __("Currently sequential (one by one)", "wpmudev-plugin-test")
								: __("Currently parallel (all at once)", "wpmudev-plugin-test")
						}
						checked={uploadMode === "parallel"}
						onChange={(val) => setUploadMode(val ? "parallel" : "sequential")}
					/>
				</div>
				<div className="sui-actions-right">
					<Button
						variant="primary"
						onClick={handleUpload}
						disabled={isUploading || !selectedFiles.length}
					>
						{isUploading ? (
							<Spinner />
						) : (
							__("Upload Files", "wpmudev-plugin-test")
						)}
					</Button>
					<span className={`upload-mode-badge ${uploadMode}`}>
						{uploadMode === "sequential"
							? __("Sequential", "wpmudev-plugin-test")
							: __("Parallel", "wpmudev-plugin-test")}
					</span>
				</div>
			</div>
		</div>
	);
};

export default UploadBox;
