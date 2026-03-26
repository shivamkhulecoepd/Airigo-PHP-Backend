<?php

namespace App\Core\Models;

class Job
{
    public int $id;
    public int $recruiter_user_id;
    public string $company_name;
    public ?string $company_logo_url;
    public string $designation;
    public string $ctc;
    public string $location;
    public string $category;
    public ?string $description;
    public ?array $requirements;
    public ?array $skills_required;
    public ?string $experience_required;
    public bool $is_active;
    public string $approval_status;
    public bool $is_urgent_hiring;
    public string $job_type;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = (int)$data['id'];
        $this->recruiter_user_id = (int)$data['recruiter_user_id'];
        $this->company_name = $data['company_name'];
        $this->company_logo_url = $data['company_logo_url'] ?? null;
        $this->designation = $data['designation'];
        $this->ctc = $data['ctc'];
        $this->location = $data['location'];
        $this->category = $data['category'];
        $this->description = $data['description'] ?? null;
        $this->requirements = isset($data['requirements']) ? json_decode($data['requirements'], true) : null;
        $this->skills_required = isset($data['skills_required']) ? json_decode($data['skills_required'], true) : null;
        $this->experience_required = $data['experience_required'] ?? null;
        $this->is_active = (bool)$data['is_active'];
        $this->approval_status = $data['approval_status'] ?? 'pending';
        $this->is_urgent_hiring = (bool)($data['is_urgent_hiring'] ?? false);
        $this->job_type = $data['job_type'] ?? 'Full-time';
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'recruiter_user_id' => $this->recruiter_user_id,
            'company_name' => $this->company_name,
            'company_logo_url' => $this->company_logo_url,
            'designation' => $this->designation,
            'ctc' => $this->ctc,
            'location' => $this->location,
            'category' => $this->category,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'skills_required' => $this->skills_required,
            'experience_required' => $this->experience_required,
            'is_active' => $this->is_active,
            'approval_status' => $this->approval_status,
            'is_urgent_hiring' => $this->is_urgent_hiring,
            'job_type' => $this->job_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isUrgentHiring(): bool
    {
        return $this->is_urgent_hiring;
    }

    public function isFullTime(): bool
    {
        return $this->job_type === 'Full-time';
    }

    public function isPartTime(): bool
    {
        return $this->job_type === 'Part-time';
    }

    public function isContract(): bool
    {
        return $this->job_type === 'Contract';
    }

    public function isInternship(): bool
    {
        return $this->job_type === 'Internship';
    }
}