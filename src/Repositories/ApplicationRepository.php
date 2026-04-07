<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Models\Application;

class ApplicationRepository extends BaseRepository
{
    protected string $table = 'applications';
    protected string $primaryKey = 'id';

    public function findByJobId(int $jobId, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE job_id = ?";
        $params = [$jobId];

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

    public function findByJobseekerId(int $jobseekerUserId, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE jobseeker_user_id = ?";
        $params = [$jobseekerUserId];

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

    public function findUniqueJobseekersForJob(int $jobId): array
    {
        $query = "SELECT DISTINCT jobseeker_user_id FROM {$this->table} WHERE job_id = ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$jobId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findUniqueJobsForJobseeker(int $jobseekerUserId): array
    {
        $query = "SELECT DISTINCT job_id FROM {$this->table} WHERE jobseeker_user_id = ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$jobseekerUserId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getApplicationStatsForJob(int $jobId): array
    {
        $query = "
            SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'shortlisted' THEN 1 END) as shortlisted,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted
            FROM {$this->table}
            WHERE job_id = ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$jobId]);
        return $stmt->fetch();
    }

    public function getApplicationStatsForJobseeker(int $jobseekerUserId): array
    {
        $query = "
            SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'shortlisted' THEN 1 END) as shortlisted,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted
            FROM {$this->table}
            WHERE jobseeker_user_id = ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$jobseekerUserId]);
        return $stmt->fetch();
    }

    public function hasApplied(int $jobId, int $jobseekerUserId): bool
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE job_id = ? AND jobseeker_user_id = ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$jobId, $jobseekerUserId]);
        $result = $stmt->fetch();

        return (int)$result['count'] > 0;
    }

    public function create(array $data): int
    {
        // Ensure we only insert allowed columns
        $allowedColumns = [
            'job_id', 'recruiter_user_id', 'jobseeker_user_id', 'resume_url', 'cover_letter', 'status'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::create($filteredData);
    }

    public function update(int $id, array $data): bool
    {
        // Ensure we only update allowed columns
        $allowedColumns = [
            'job_id', 'recruiter_user_id', 'jobseeker_user_id', 'resume_url', 'cover_letter', 'status'
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedColumns));

        return parent::update($id, $filteredData);
    }

    public function getApplicationWithDetails(int $applicationId): ?array
    {
        $query = "
            SELECT 
                a.*,
                a.recruiter_user_id,
                j.designation,
                j.company_name,
                j.company_url,
                j.location,
                j.category,
                j.job_type,
                jr.recruiter_name,
                jr.company_website,
                u.email as jobseeker_email,
                u.phone as jobseeker_phone,
                js.name as jobseeker_name,
                js.profile_image_url as jobseeker_photo_url,
                js.skills as jobseeker_skills,
                js.bio as jobseeker_bio,
                js.qualification as jobseeker_qualification,
                js.experience as jobseeker_experience
            FROM {$this->table} a
            JOIN jobs j ON a.job_id = j.id
            LEFT JOIN recruiters jr ON a.recruiter_user_id = jr.user_id
            JOIN users u ON a.jobseeker_user_id = u.id
            LEFT JOIN jobseekers js ON a.jobseeker_user_id = js.user_id
            WHERE a.id = ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$applicationId]);
        return $stmt->fetch() ?: null;
    }

    public function getApplicationsForRecruiter(int $recruiterUserId, array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "
            SELECT 
                a.*, 
                j.designation, 
                j.company_name, 
                j.company_url,
                j.location, 
                j.category,
                j.job_type,
                jr.recruiter_name, 
                jr.company_website, 
                u.email as jobseeker_email, 
                u.phone as jobseeker_phone,
                js.name as jobseeker_name,
                js.profile_image_url as jobseeker_photo_url,
                js.skills as jobseeker_skills,
                js.bio as jobseeker_bio,
                js.qualification as jobseeker_qualification,
                js.experience as jobseeker_experience
            FROM {$this->table} a 
            JOIN jobs j ON a.job_id = j.id 
            JOIN users u ON a.jobseeker_user_id = u.id 
            LEFT JOIN jobseekers js ON a.jobseeker_user_id = js.user_id 
            LEFT JOIN recruiters jr ON a.recruiter_user_id = jr.user_id 
        ";
        
        $params = [];
        $conditions = [];

        // Add recruiter filter
        $conditions[] = "j.recruiter_user_id = ?";
        $params[] = $recruiterUserId;

        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                if ($column === 'status') {
                    $conditions[] = "a.status = ?";
                    $params[] = $value;
                } elseif ($column === 'job_id') {
                    $conditions[] = "a.job_id = ?";
                    $params[] = $value;
                }
            }
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY a.{$this->primaryKey} DESC";

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

    public function getApplicationsWithJobDetails(array $filters = [], int $limit = null, int $offset = null): array
    {
        $query = "
            SELECT 
                a.*,
                a.recruiter_user_id,
                j.designation,
                j.company_name,
                j.company_url,
                j.location,
                j.category,
                j.job_type,
                jr.recruiter_name,
                jr.company_website,
                u.email as jobseeker_email,
                u.phone as jobseeker_phone,
                js.name as jobseeker_name,
                js.profile_image_url as jobseeker_photo_url,
                js.skills as jobseeker_skills,
                js.bio as jobseeker_bio,
                js.qualification as jobseeker_qualification,
                js.experience as jobseeker_experience
            FROM {$this->table} a
            JOIN jobs j ON a.job_id = j.id
            LEFT JOIN recruiters jr ON a.recruiter_user_id = jr.user_id
            JOIN users u ON a.jobseeker_user_id = u.id
            LEFT JOIN jobseekers js ON a.jobseeker_user_id = js.user_id
        ";
        
        $params = [];
        $conditions = [];

        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                if ($column === 'job_id' || $column === 'jobseeker_user_id' || $column === 'status') {
                    $conditions[] = "a.{$column} = ?";
                    $params[] = $value;
                }
            }
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY a.{$this->primaryKey} DESC";

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
                COUNT(*) as total_applications,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'shortlisted' THEN 1 END) as shortlisted,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted
            FROM {$this->table}
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
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

    public function countForRecruiter(int $recruiterUserId, array $filters = []): int
    {
        $query = "
            SELECT COUNT(*) as count 
            FROM {$this->table} a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE j.recruiter_user_id = ?
        ";
        
        $params = [$recruiterUserId];
        $conditions = [];

        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                if ($column === 'status') {
                    $conditions[] = "a.status = ?";
                    $params[] = $value;
                } elseif ($column === 'job_id') {
                    $conditions[] = "a.job_id = ?";
                    $params[] = $value;
                }
            }
        }

        if (!empty($conditions)) {
            $query .= " AND " . implode(' AND ', $conditions);
        }

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
}