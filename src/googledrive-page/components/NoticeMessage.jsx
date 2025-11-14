import { Notice } from "@wordpress/components";

/**
 * NoticeMessage
 * Displays a dismissible WordPress notice based on `notice` object.
 *
 * Props:
 * - notice: { message: string, status: "success" | "error" }
 * - onDismiss: function to call when notice is dismissed
 */
const NoticeMessage = ({ notice, onDismiss }) => {
	if (!notice?.message) return null;

	return (
		<Notice
			status={notice.status === "error" ? "error" : "success"}
			isDismissible
			onRemove={onDismiss}
		>
			{notice.message}
		</Notice>
	);
};

export default NoticeMessage;
