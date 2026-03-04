<?php

namespace App\Repositories;

use App\Core\Database\BaseRepository;

class PasswordResetTokenRepository extends BaseRepository
{
    protected string $table = 'password_reset_tokens';
    protected string $primaryKey = 'id';

    public function createToken(int $userId, string $token, int $expiresInHours = 24): int
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresInHours hours"));
        
        $data = [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt
        ];

        return parent::create($data);
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->connection->prepare("
            SELECT prt.*, u.email, u.phone, u.user_type 
            FROM {$this->table} prt 
            JOIN users u ON prt.user_id = u.id 
            WHERE prt.token = ? AND prt.used = FALSE AND prt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function markAsUsed(int $tokenId): bool
    {
        return $this->update($tokenId, ['used' => true]);
    }

    public function deleteExpiredTokens(): int
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->table} WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function deleteByUserId(int $userId): int
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}