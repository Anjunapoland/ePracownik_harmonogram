<?php
/**
 * includes/notification_helper.php
 * Helper do tworzenia rekordów w tabeli notifications.
 */

if (!function_exists('desktop_notification_add')) {
    function desktop_notification_add(PDO $db, $userId, $type, $title, $body = null, $relatedDate = null)
    {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, body, related_date, is_read)
            VALUES (:user_id, :type, :title, :body, :related_date, 0)
        ");
        $stmt->execute(array(
            'user_id' => (int)$userId,
            'type' => (string)$type,
            'title' => (string)$title,
            'body' => $body,
            'related_date' => $relatedDate
        ));
    }
}

if (!function_exists('desktop_notification_add_many')) {
    function desktop_notification_add_many(PDO $db, array $userIds, $type, $title, $body = null, $relatedDate = null)
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        foreach ($userIds as $uid) {
            if ($uid > 0) {
                desktop_notification_add($db, $uid, $type, $title, $body, $relatedDate);
            }
        }
    }
}
