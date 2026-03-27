<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;

class RecruiterRepository extends BaseRepository
{
    protected string $table = 'recruiters';
    protected string $primaryKey = 'user_id';

    public function create(array $data): int
    {
        // Ensure we only insert allowed columns
        $allowedColumns = [
            'user_id', 'email', 'recruiter_name', 'company_name', 'company_website', 'designation', 'location', 
            'photo_url', 'id_card_url', 'approval_status', 'approved_by', 
            'approved_at', 'rejection_reason'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::create($filteredData);
    }

    public function update(int $id, array $data): bool
    {
        // Ensure we only update allowed columns
        $allowedColumns = [
            'email', 'recruiter_name', 'company_name', 'company_website', 'designation', 'location', 
            'photo_url', 'id_card_url', 'approval_status', 'approved_by', 
            'approved_at', 'rejection_reason'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::update($id, $filteredData);
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findByApprovalStatus(string $status, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE approval_status = ?";
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

    public function findByCompanyName(string $companyName, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE company_name LIKE ?";
        $params = ["%{$companyName}%"];

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

    public function findByLocation(string $location, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE location LIKE ?";
        $params = ["%{$location}%"];

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

    public function getRecruiterWithUserDetails(int $userId): ?array
    {
        $query = "
            SELECT 
                r.user_id,
                r.email,
                r.recruiter_name,
                r.company_name,
                r.company_website,
                r.designation,
                r.location,
                r.photo_url,
                r.id_card_url,
                r.approval_status,
                r.approved_by,
                r.approved_at,
                r.rejection_reason,
                r.created_at,
                r.updated_at,
                u.email as user_email,
                u.phone,
                u.status,
                u.email_verified,
                u.created_at as user_created_at,
                u.updated_at as user_updated_at
            FROM {$this->table} r
            JOIN users u ON r.user_id = u.id
            WHERE r.user_id = ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function getRecruiterStats(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total_recruiters,
                COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as approved_recruiters,
                COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending_recruiters,
                COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) as rejected_recruiters,
                (SELECT COUNT(DISTINCT company_name) FROM {$this->table}) as unique_companies,
                (SELECT COUNT(DISTINCT location) FROM {$this->table}) as unique_locations
            FROM {$this->table}
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function approveRecruiter(int $userId, int $approvedBy): bool
    {
        $query = "UPDATE {$this->table} 
                  SET approval_status = 'approved', 
                      approved_by = ?, 
                      approved_at = NOW() 
                  WHERE user_id = ?";
        
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([$approvedBy, $userId]);
    }

    public function rejectRecruiter(int $userId, string $rejectionReason, int $approvedBy): bool
    {
        $query = "UPDATE {$this->table} 
                  SET approval_status = 'rejected', 
                      rejection_reason = ?, 
                      approved_by = ?, 
                      approved_at = NOW() 
                  WHERE user_id = ?";
        
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([$rejectionReason, $approvedBy, $userId]);
    }

    public function searchRecruiters(array $searchParams = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($searchParams)) {
            $conditions = [];
            
            if (isset($searchParams['company_name'])) {
                $conditions[] = "company_name LIKE ?";
                $params[] = "%{$searchParams['company_name']}%";
            }
            
            if (isset($searchParams['location'])) {
                $conditions[] = "location LIKE ?";
                $params[] = "%{$searchParams['location']}%";
            }
            
            if (isset($searchParams['approval_status'])) {
                $conditions[] = "approval_status = ?";
                $params[] = $searchParams['approval_status'];
            }
            
            if (isset($searchParams['designation'])) {
                $conditions[] = "designation LIKE ?";
                $params[] = "%{$searchParams['designation']}%";
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(' AND ', $conditions);
            }
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