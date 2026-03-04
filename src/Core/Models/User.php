<?php

namespace App\Core\Models;

class User
{
    public int $id;
    public string $email;
    public string $password_hash;
    public string $user_type;
    public ?string $phone;
    public string $status;
    public bool $email_verified;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = (int)$data['id'];
        $this->email = $data['email'];
        $this->password_hash = $data['password_hash'];
        $this->user_type = $data['user_type'];
        $this->phone = $data['phone'] ?? null;
        $this->status = $data['status'] ?? 'active';
        $this->email_verified = (bool)($data['email_verified'] ?? false);
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'phone' => $this->phone,
            'status' => $this->status,
            'email_verified' => $this->email_verified,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function isJobSeeker(): bool
    {
        return $this->user_type === 'jobseeker';
    }

    public function isRecruiter(): bool
    {
        return $this->user_type === 'recruiter';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}