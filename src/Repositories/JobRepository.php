<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Models\Job;

class JobRepository extends BaseRepository
{
    protected string $table = 'jobs';
    protected string $primaryKey = 'id';

    public function findByRecruiter(int $recruiterUserId, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE recruiter_user_id = ?";
        $params = [$recruiterUserId];

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

    public function findActiveJobs(array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];

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

    public function findApprovedJobs(array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE approval_status = 'approved' AND is_active = 1";
        $params = [];

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

    public function searchJobs(array $searchParams = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE is_active = 1 AND approval_status = 'approved'";
        $params = [];

        if (!empty($searchParams)) {
            $conditions = [];
            
            if (isset($searchParams['location'])) {
                $conditions[] = "location LIKE ?";
                $params[] = "%{$searchParams['location']}%";
            }
            
            if (isset($searchParams['category'])) {
                $conditions[] = "category = ?";
                $params[] = $searchParams['category'];
            }
            
            if (isset($searchParams['designation'])) {
                $conditions[] = "designation LIKE ?";
                $params[] = "%{$searchParams['designation']}%";
            }
            
            if (isset($searchParams['company_name'])) {
                $conditions[] = "company_name LIKE ?";
                $params[] = "%{$searchParams['company_name']}%";
            }
            
            if (isset($searchParams['job_type'])) {
                $conditions[] = "job_type = ?";
                $params[] = $searchParams['job_type'];
            }
            
            if (isset($searchParams['min_ctc'])) {
                $conditions[] = "CAST(REPLACE(ctc, 'LPA', '') AS DECIMAL(10,2)) >= ?";
                $params[] = $searchParams['min_ctc'];
            }
            
            if (isset($searchParams['max_ctc'])) {
                $conditions[] = "CAST(REPLACE(ctc, 'LPA', '') AS DECIMAL(10,2)) <= ?";
                $params[] = $searchParams['max_ctc'];
            }
            
            if (isset($searchParams['experience_required'])) {
                $conditions[] = "experience_required = ?";
                $params[] = $searchParams['experience_required'];
            }
            
            if (isset($searchParams['is_urgent_hiring'])) {
                $conditions[] = "is_urgent_hiring = ?";
                $params[] = $searchParams['is_urgent_hiring'];
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

    public function getJobsByCategory(string $category, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE category = ? AND is_active = 1 AND approval_status = 'approved'";
        $params = [$category];

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

    public function getJobsByLocation(string $location, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE location LIKE ? AND is_active = 1 AND approval_status = 'approved'";
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

    public function getPendingJobs(array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE approval_status = 'pending'";
        $params = [];

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
            'recruiter_user_id', 'company_name', 'designation', 'ctc', 'location', 
            'category', 'description', 'requirements', 'skills_required', 
            'experience_required', 'is_active', 'approval_status', 'is_urgent_hiring', 'job_type'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::create($filteredData);
    }

    public function update(int $id, array $data): bool
    {
        // Ensure we only update allowed columns
        $allowedColumns = [
            'recruiter_user_id', 'company_name', 'designation', 'ctc', 'location', 
            'category', 'description', 'requirements', 'skills_required', 
            'experience_required', 'is_active', 'approval_status', 'is_urgent_hiring', 'job_type'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::update($id, $filteredData);
    }

    public function getStats(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total_jobs,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_jobs,
                COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as approved_jobs,
                COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending_jobs,
                COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) as rejected_jobs,
                COUNT(CASE WHEN is_urgent_hiring = 1 THEN 1 END) as urgent_jobs
            FROM {$this->table}
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getTopCategories(int $limit = 10): array
    {
        $query = "
            SELECT 
                category,
                COUNT(*) as job_count
            FROM {$this->table}
            WHERE is_active = 1 AND approval_status = 'approved'
            GROUP BY category
            ORDER BY job_count DESC
            LIMIT ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getTopLocations(int $limit = 10): array
    {
        $query = "
            SELECT 
                location,
                COUNT(*) as job_count
            FROM {$this->table}
            WHERE is_active = 1 AND approval_status = 'approved'
            GROUP BY location
            ORDER BY job_count DESC
            LIMIT ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$limit]);
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

    public function search(string $query, array $filters = [], int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE (designation LIKE ? OR company_name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $params = ["%{$query}%", "%{$query}%", "%{$query}%", "%{$query}%"];

        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                $sql .= " AND {$column} = ?";
                $params[] = $value;
            }
        }

        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}