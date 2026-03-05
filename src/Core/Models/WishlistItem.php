<?php

namespace App\Core\Models;

class WishlistItem
{
    public int $id;
    public int $userId;
    public int $jobId;
    public string $createdAt;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->userId = $data['user_id'] ?? $data['userId'] ?? 0;
        $this->jobId = $data['job_id'] ?? $data['jobId'] ?? 0;
        $this->createdAt = $data['created_at'] ?? $data['createdAt'] ?? date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'job_id' => $this->jobId,
            'created_at' => $this->createdAt
        ];
    }
}