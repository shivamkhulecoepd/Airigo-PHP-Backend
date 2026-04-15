<?php

namespace App\Repositories;

use App\Core\Database\Connection;
use PDO;

class NotificationRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Connection::getInstance();
    }

    /**
     * Create a new notification record
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO notifications (
                    user_id, 
                    title, 
                    body, 
                    type, 
                    data, 
                    is_read, 
                    created_at
                ) VALUES (
                    :user_id, 
                    :title, 
                    :body, 
                    :type, 
                    :data, 
                    :is_read, 
                    NOW()
                )";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':body', $data['body'], PDO::PARAM_STR);
        $stmt->bindValue(':type', $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(':data', json_encode($data['data']), PDO::PARAM_STR);
        $stmt->bindValue(':is_read', $data['is_read'] ?? false, PDO::PARAM_BOOL);

        $result = $stmt->execute();

        if ($result) {
            return $this->connection->lastInsertId();
        }

        return 0;
    }

    /**
     * Get notifications for a specific user
     */
    public function getByUserId(int $userId, int $limit = 50, int $offset = 0, bool $onlyUnread = false, bool $onlyArchived = false): array
    {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id";
        
        if ($onlyUnread) {
            $sql .= " AND is_read = FALSE";
        }
        
        if ($onlyArchived) {
            $sql .= " AND is_archived = TRUE";
        } else {
            $sql .= " AND is_archived = FALSE";  // Don't show archived by default
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data
        foreach ($results as &$result) {
            $result['data'] = json_decode($result['data'], true) ?: [];
        }

        return $results;
    }

    /**
     * Get notification by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM notifications WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['data'] = json_decode($result['data'], true) ?: [];
        }

        return $result ?: null;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id): bool
    {
        $sql = "UPDATE notifications SET is_read = TRUE WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): bool
    {
        $sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Count total notifications for a user
     */
    public function countByUser(int $userId, bool $onlyUnread = false): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id";
        
        if ($onlyUnread) {
            $sql .= " AND is_read = FALSE";
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Delete notification
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM notifications WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Archive a notification
     */
    public function archive(int $id): bool
    {
        $sql = "UPDATE notifications SET is_archived = TRUE WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Get archived notifications for a specific user
     */
    public function getArchivedByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id AND is_archived = TRUE
                ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data
        foreach ($results as &$result) {
            $result['data'] = json_decode($result['data'], true) ?: [];
        }

        return $results;
    }

    /**
     * Delete all notifications for a user
     */
    public function deleteByUser(int $userId): bool
    {
        $sql = "DELETE FROM notifications WHERE user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}