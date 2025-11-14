import { useState, useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Spinner, Button } from "@wordpress/components";
import { fetchPostsByType } from "../utils/fetchPostsByType";

const POSTS_PER_PAGE = 10;

const PostList = ({ selectedType, onSelect }) => {
	const [availablePosts, setAvailablePosts] = useState([]);
	const [selectedPosts, setSelectedPosts] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [searchTerm, setSearchTerm] = useState("");
	const [currentPage, setCurrentPage] = useState(1);

	useEffect(() => {
		if (!selectedType) return;

		const fetchPosts = async () => {
			setIsLoading(true);
			try {
				await fetchPostsByType(
					selectedType,
					window.WPMUDEV_PM.nonce,
					window.WPMUDEV_PM.ajax_url,
					setAvailablePosts
				);
				setCurrentPage(1);
				setSelectedPosts([]);
				onSelect([]); // Reset selection when type changes
			} catch (e) {
				console.error("Failed to fetch posts:", e);
			} finally {
				setIsLoading(false);
			}
		};

		fetchPosts();
	}, [selectedType]);

	const filteredPosts = availablePosts.filter((post) =>
		post.title.toLowerCase().includes(searchTerm.toLowerCase())
	);

	const totalPages = Math.ceil(filteredPosts.length / POSTS_PER_PAGE);
	const paginatedPosts = filteredPosts.slice(
		(currentPage - 1) * POSTS_PER_PAGE,
		currentPage * POSTS_PER_PAGE
	);

	const handleCheckboxChange = (id) => {
		const updated = selectedPosts.includes(id)
			? selectedPosts.filter((pid) => pid !== id)
			: [...selectedPosts, id];

		setSelectedPosts(updated);
		onSelect(updated);
	};

	const handleSelectAll = () => {
		const visibleIds = paginatedPosts.map((p) => p.id);
		const allSelected = visibleIds.every((id) => selectedPosts.includes(id));

		const updated = allSelected
			? selectedPosts.filter((id) => !visibleIds.includes(id))
			: [...new Set([...selectedPosts, ...visibleIds])];

		setSelectedPosts(updated);
		onSelect(updated);
	};

	if (!selectedType) return null;

	return (
		<div className="pm-post-list">
			<h4>{__("Select posts to scan:", "wpmudev-plugin-test")}</h4>

			<input
				type="search"
				placeholder={__("Search posts...", "wpmudev-plugin-test")}
				value={searchTerm}
				onChange={(e) => setSearchTerm(e.target.value)}
				style={{ marginBottom: "10px", width: "100%" }}
			/>

			{isLoading ? (
				<Spinner />
			) : filteredPosts.length > 0 ? (
				<>
					<div style={{ marginBottom: "10px" }}>
						<label>
							<input
								type="checkbox"
								checked={
									paginatedPosts.every((p) => selectedPosts.includes(p.id)) &&
									paginatedPosts.length > 0
								}
								onChange={handleSelectAll}
							/>{" "}
							{__("Select All on Page", "wpmudev-plugin-test")}
						</label>
					</div>

					<ul>
						{paginatedPosts.map((post) => (
							<li key={post.id}>
								<label>
									<input
										type="checkbox"
										value={post.id}
										checked={selectedPosts.includes(post.id)}
										onChange={() => handleCheckboxChange(post.id)}
									/>
									{post.title || `Post #${post.id}`}
								</label>
							</li>
						))}
					</ul>

					<div style={{ marginTop: "10px" }}>
						<Button
							isSecondary
							disabled={currentPage === 1}
							onClick={() => setCurrentPage((p) => p - 1)}
						>
							{__("Previous", "wpmudev-plugin-test")}
						</Button>{" "}
						<Button
							isSecondary
							disabled={currentPage === totalPages}
							onClick={() => setCurrentPage((p) => p + 1)}
						>
							{__("Next", "wpmudev-plugin-test")}
						</Button>
						<span style={{ marginLeft: "10px" }}>
							{__("Page", "wpmudev-plugin-test")} {currentPage} / {totalPages}
						</span>
					</div>
				</>
			) : (
				<p>{__("No posts found for this type.", "wpmudev-plugin-test")}</p>
			)}
		</div>
	);
};

export default PostList;
