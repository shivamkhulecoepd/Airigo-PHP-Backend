<?php

namespace App\Core\Models;

class Application
{
    public int $id;
    public int $job_id;
    public int $jobseeker_user_id;
    public ?string $resume_url;
    public ?string $cover_letter;
    public string $status;
    public string $applied_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = (int)$data['id'];
        $this->job_id = (int)$data['job_id'];
        $this->jobseeker_user_id = (int)$data['jobseeker_user_id'];
        $this->resume_url = $data['resume_url'] ?? null;
        $this->cover_letter = $data['cover_letter'] ?? null;
        $this->status = $data['status'] ?? 'pending';
        $this->applied_at = $data['applied_at'];
        $this->updated_at = $data['updated_at'];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'jobseeker_user_id' => $this->jobseeker_user_id,
            'resume_url' => $this->resume_url,
            'cover_letter' => $this->cover_letter,
            'status' => $this->status,
            'applied_at' => $this->applied_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isShortlisted(): bool
    {
        return $this->status === 'shortlisted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function getStatusLabel(): string
    {
        $labels = [
            'pending' => 'Pending Review',
            'shortlisted' => 'Shortlisted',
            'rejected' => 'Rejected',
            'accepted' => 'Accepted'
        ];

        return $labels[$this->status] ?? $this->status;
    }
}