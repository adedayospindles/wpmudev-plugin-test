import { Notice } from "@wordpress/components";

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
