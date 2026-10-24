<?php
namespace App\Controllers;

use Core\Controller;
use Core\Database;

class NotificationController extends Controller {
    public function unread() {
        $user = auth();
        if (!$user) {
            return json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $db = Database::getInstance();
        $notifications = $db->query(
            "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC",
            [$user->id]
        )->fetchAll();

        return json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    public function markAllRead() {
        $user = auth();
        if (!$user) {
            return json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $db = Database::getInstance();
        $db->query(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$user->id]
        );

        return json(['success' => true]);
    }
}
