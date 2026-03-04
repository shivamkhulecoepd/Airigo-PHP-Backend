<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Models\User;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();

        return $result ?: null;
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

    public function create(array $data): int
    {
        // Ensure we only insert allowed columns
        $allowedColumns = [
            'email', 'password_hash', 'user_type', 'phone', 'status', 'email_verified'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::create($filteredData);
    }

    public function update(int $id, array $data): bool
    {
        // Ensure we only update allowed columns
        $allowedColumns = [
            'email', 'password_hash', 'user_type', 'phone', 'status', 'email_verified'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::update($id, $filteredData);
    }

    public function getTotalCountByUserType(string $userType): int
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_type = ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$userType]);
        $result = $stmt->fetch();

        return (int)($result['count'] ?? 0);
    }

    public function getTotalCountByStatus(string $status): int
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$status]);
        $result = $stmt->fetch();

        return (int)($result['count'] ?? 0);
    }

    public function searchByEmail(string $email): array
    {
        $query = "SELECT * FROM {$this->table} WHERE email LIKE ? ORDER BY {$this->primaryKey} DESC";
        $stmt = $this->connection->prepare($query);
        $stmt->execute(["%{$email}%"]);
        return $stmt->fetchAll();
    }

    public function getPaginated(int $page = 1, int $limit = 10, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY {$this->primaryKey} DESC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Get total count for pagination metadata
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
        $countParams = [];
        
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "{$column} = ?";
                $countParams[] = $value;
            }
            $countQuery .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $countStmt = $this->connection->prepare($countQuery);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];

        return [
            'data' => $results,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
}