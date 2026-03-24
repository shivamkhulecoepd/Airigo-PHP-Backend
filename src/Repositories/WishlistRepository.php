<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Models\WishlistItem;

class WishlistRepository extends BaseRepository
{
    protected string $table = 'wishlist_items';
    protected string $modelClass = WishlistItem::class;

    /**
     * Check if a job is in a user's wishlist
     */
    public function isInWishlist(int $userId, int $jobId): bool
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ? AND job_id = ?"
        );
        $stmt->execute([$userId, $jobId]);
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Add a job to a user's wishlist
     */
    public function addToWishlist(int $userId, int $jobId): bool
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO {$this->table} (user_id, job_id) VALUES (?, ?)"
        );
        
        try {
            return $stmt->execute([$userId, $jobId]);
        } catch (\PDOException $e) {
            // Handle duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return true; // Already exists, treat as success
            }
            throw $e;
        }
    }

    /**
     * Remove a job from a user's wishlist
     */
    public function removeFromWishlist(int $userId, int $jobId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM {$this->table} WHERE user_id = ? AND job_id = ?"
        );
        return $stmt->execute([$userId, $jobId]);
    }

    /**
     * Get all wishlist items for a user
     */
    public function getWishlistByUser(int $userId, int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT w.*, j.*, r.company_name 
                FROM {$this->table} w
                JOIN jobs j ON w.job_id = j.id
                LEFT JOIN recruiters r ON j.recruiter_user_id = r.user_id
                WHERE w.user_id = ?
                ORDER BY w.created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$userId, $limit, $offset]);
        } else {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$userId]);
        }
        
        $wishlistItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Decode JSON fields in jobs
        foreach ($wishlistItems as &$item) {
            if (!empty($item['requirements'])) {
                $decoded = json_decode($item['requirements'], true);
                $item['requirements'] = $decoded ?? json_decode($item['requirements'], false) ?? $item['requirements'];
            }
            if (!empty($item['skills_required'])) {
                $decoded = json_decode($item['skills_required'], true);
                $item['skills_required'] = $decoded ?? json_decode($item['skills_required'], false) ?? $item['skills_required'];
            }
        }
        
        return $wishlistItems;
    }

    /**
     * Get count of wishlist items for a user
     */
    public function getWishlistCount(int $userId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        return (int)$result['count'];
    }

    /**
     * Get all wishlist item IDs for a user
     */
    public function getWishlistJobIds(int $userId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT job_id FROM {$this->table} WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return (int)$row['job_id'];
        }, $results);
    }

    /**
     * Remove all wishlist items for a specific job (when job is deleted)
     */
    public function removeByJobId(int $jobId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM {$this->table} WHERE job_id = ?"
        );
        return $stmt->execute([$jobId]);
    }

    /**
     * Remove all wishlist items for a specific user (when user is deleted)
     */
    public function removeByUserId(int $userId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM {$this->table} WHERE user_id = ?"
        );
        return $stmt->execute([$userId]);
    }
}