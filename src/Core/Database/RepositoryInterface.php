<?php

namespace App\Core\Database;

interface RepositoryInterface
{
    /**
     * Find record by ID
     */
    public function findById(int $id);

    /**
     * Get all records
     */
    public function findAll(array $filters = [], int $limit = null, int $offset = null);

    /**
     * Create a new record
     */
    public function create(array $data);

    /**
     * Update an existing record
     */
    public function update(int $id, array $data);

    /**
     * Delete a record by ID
     */
    public function delete(int $id);

    /**
     * Count total records
     */
    public function count(array $filters = []): int;
}