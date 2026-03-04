<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;

class IssueReportRepository extends BaseRepository
{
    protected string $table = 'issues_reports';
    protected string $primaryKey = 'id';

    public function create(array $data): int
    {
        // Ensure we only insert allowed columns
        $allowedColumns = [
            'user_id', 'user_type', 'type', 'title', 'description', 
            'status', 'admin_response'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::create($filteredData);
    }

    public function update(int $id, array $data): bool
    {
        // Ensure we only update allowed columns
        $allowedColumns = [
            'user_type', 'type', 'title', 'description', 
            'status', 'admin_response'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::update($id, $filteredData);
    }

    public function findByUserId(int $userId, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " AND " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY {$this->primaryKey} DESC";

        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByType(string $type, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE type = ?";
        $params = [$type];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " AND " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY {$this->primaryKey} DESC";

        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByStatus(string $status, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE status = ?";
        $params = [$status];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " AND " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY {$this->primaryKey} DESC";

        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByUserType(string $userType, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE user_type = ?";
        $params = [$userType];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " AND " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY {$this->primaryKey} DESC";

        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStats(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total_reports,
                COUNT(CASE WHEN type = 'issue' THEN 1 END) as total_issues,
                COUNT(CASE WHEN type = 'report' THEN 1 END) as total_reports,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved
            FROM {$this->table}
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getRecentReports(int $limit = 10): array
    {
        $query = "
            SELECT * 
            FROM {$this->table}
            ORDER BY {$this->primaryKey} DESC
            LIMIT ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getReportCountsByType(): array
    {
        $query = "
            SELECT 
                type,
                COUNT(*) as count
            FROM {$this->table}
            GROUP BY type
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getReportCountsByStatus(): array
    {
        $query = "
            SELECT 
                status,
                COUNT(*) as count
            FROM {$this->table}
            GROUP BY status
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getReportCountsByUserType(): array
    {
        $query = "
            SELECT 
                user_type,
                COUNT(*) as count
            FROM {$this->table}
            GROUP BY user_type
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}