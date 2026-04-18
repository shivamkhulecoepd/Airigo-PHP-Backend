<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;

class JobseekerRepository extends BaseRepository
{
    protected string $table = 'jobseekers';
    protected string $primaryKey = 'user_id';

    public function create(array $data): int
    {
        // Ensure we only insert allowed columns
        $allowedColumns = [
            'user_id', 'name', 'qualification', 'experience', 'location', 
            'date_of_birth', 'resume_url', 'resume_filename', 'profile_image_url', 
            'skills', 'bio'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::create($filteredData);
    }

    public function update(int $id, array $data): bool
    {
        // Ensure we only update allowed columns
        $allowedColumns = [
            'name', 'qualification', 'experience', 'location', 
            'date_of_birth', 'resume_url', 'resume_filename', 'profile_image_url', 
            'skills', 'bio'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::update($id, $filteredData);
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        // Decode JSON fields
        if ($result) {
            $this->decodeJsonFields($result);
        }

        return $result ?: null;
    }

    public function findByName(string $name, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE name LIKE ?";
        $params = ["%{$name}%"];

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
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }
        
        return $results;
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
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }
        
        return $results;
    }

    public function findByExperience(int $minExperience, int $maxExperience = null, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE experience >= ?";
        $params = [$minExperience];

        if ($maxExperience !== null) {
            $query .= " AND experience <= ?";
            $params[] = $maxExperience;
        }

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
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }
        
        return $results;
    }

    public function findBySkills(array $skills, array $filters = [], int $limit = null, int $offset = null): array
    {
        // Create a query to find jobseekers who have any of the specified skills
        $query = "SELECT * FROM {$this->table} WHERE ";
        $orConditions = [];
        $params = [];

        foreach ($skills as $skill) {
            $orConditions[] = "JSON_CONTAINS(skills, '\"{$skill}\"')";
        }

        $query .= "(" . implode(' OR ', $orConditions) . ")";

        if (!empty($filters)) {
            $andConditions = [];
            foreach ($filters as $column => $value) {
                $andConditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " AND " . implode(' AND ', $andConditions);
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
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }
        
        return $results;
    }

    public function getJobseekerWithUserDetails(int $userId): ?array
    {
        $query = "
            SELECT 
                j.*,
                u.email,
                u.phone,
                u.status,
                u.email_verified,
                u.created_at as user_created_at,
                u.updated_at as user_updated_at
            FROM {$this->table} j
            JOIN users u ON j.user_id = u.id
            WHERE j.user_id = ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        // Decode JSON fields
        if ($result) {
            $this->decodeJsonFields($result);
        }
        
        return $result ?: null;
    }

    public function getJobseekerStats(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total_jobseekers,
                AVG(experience) as avg_experience,
                (SELECT COUNT(DISTINCT location) FROM {$this->table}) as unique_locations
            FROM {$this->table}
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function searchJobseekers(array $searchParams = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($searchParams)) {
            $conditions = [];
            
            if (isset($searchParams['name'])) {
                $conditions[] = "name LIKE ?";
                $params[] = "%{$searchParams['name']}%";
            }
            
            if (isset($searchParams['location'])) {
                $conditions[] = "location LIKE ?";
                $params[] = "%{$searchParams['location']}%";
            }
            
            if (isset($searchParams['min_experience'])) {
                $conditions[] = "experience >= ?";
                $params[] = $searchParams['min_experience'];
            }
            
            if (isset($searchParams['max_experience'])) {
                $conditions[] = "experience <= ?";
                $params[] = $searchParams['max_experience'];
            }
            
            if (isset($searchParams['skills']) && is_array($searchParams['skills'])) {
                foreach ($searchParams['skills'] as $skill) {
                    $conditions[] = "JSON_CONTAINS(skills, '\"{$skill}\"')";
                }
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
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }
        
        return $results;
    }

    public function getPaginated(int $page = 1, int $limit = 10, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        // JOIN with users table to get complete jobseeker data
        $query = "
            SELECT 
                j.user_id,
                j.name,
                j.qualification,
                j.experience,
                j.location,
                j.date_of_birth,
                j.resume_url,
                j.resume_filename,
                j.profile_image_url,
                j.skills,
                j.bio,
                j.created_at,
                j.updated_at,
                u.email,
                u.phone,
                u.status,
                u.email_verified,
                u.user_type,
                u.created_at as user_created_at
            FROM {$this->table} j
            LEFT JOIN users u ON j.user_id = u.id
        ";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                // Prefix jobseeker-specific columns with 'j.'
                $columnName = in_array($column, ['email', 'status', 'phone']) ? "j.{$column}" : $column;
                $conditions[] = "{$columnName} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY j.{$this->primaryKey} DESC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }

        // Get total count for pagination metadata
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table} j LEFT JOIN users u ON j.user_id = u.id";
        $countParams = [];
        
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $columnName = in_array($column, ['email', 'status', 'phone']) ? "j.{$column}" : $column;
                $conditions[] = "{$columnName} = ?";
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

    public function searchByName(string $name, int $limit = 10): array
    {
        $query = "SELECT * FROM {$this->table} WHERE name LIKE ? ORDER BY {$this->primaryKey} DESC LIMIT ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute(["%{$name}%", $limit]);
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $this->decodeJsonFields($result);
        }
        
        return $results;
    }

    /**
     * Decode JSON fields to proper arrays/objects
     */
    private function decodeJsonFields(array &$result): void
    {
        // Decode skills field
        if (!empty($result['skills'])) {
            $decoded = json_decode($result['skills'], true);
            $result['skills'] = $decoded ?? json_decode($result['skills'], false) ?? $result['skills'];
        }
    }

    public function findById(int $id, bool $useCache = false)
    {
        $result = parent::findById($id, $useCache);
        
        // Decode JSON fields
        if ($result) {
            $this->decodeJsonFields($result);
        }
        
        return $result;
    }
}