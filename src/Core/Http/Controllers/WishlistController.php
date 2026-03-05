<?php

namespace App\Core\Http\Controllers;

use App\Repositories\WishlistRepository;
use App\Repositories\JobRepository;
use App\Core\Utils\ResponseBuilder;

class WishlistController extends BaseController
{
    private WishlistRepository $wishlistRepository;
    private JobRepository $jobRepository;

    public function __construct()
    {
        parent::__construct();
        $this->wishlistRepository = new WishlistRepository();
        $this->jobRepository = new JobRepository();
    }

    /**
     * Add a job to user's wishlist
     */
    public function addToWishlist($request)
    {
        try {
            $userId = $request->getAttribute('user_id');
            $data = json_decode($request->getBody()->getContents(), true);

            // Validate input
            $validationErrors = $this->validator->validate([
                'job_id' => $data['job_id'] ?? null
            ], [
                'job_id' => 'required|numeric|min:1'
            ]);

            if (!empty($validationErrors)) {
                return ResponseBuilder::unprocessableEntity([
                    'message' => 'Validation failed',
                    'errors' => $validationErrors
                ]);
            }

            $jobId = (int)$data['job_id'];

            // Check if job exists
            $job = $this->jobRepository->findById($jobId);
            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            // Add to wishlist
            $success = $this->wishlistRepository->addToWishlist($userId, $jobId);

            if ($success) {
                return ResponseBuilder::ok([
                    'message' => 'Job added to wishlist successfully',
                    'job_id' => $jobId
                ]);
            } else {
                return ResponseBuilder::serverError(['message' => 'Failed to add job to wishlist']);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError(['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove a job from user's wishlist
     */
    public function removeFromWishlist($request)
    {
        try {
            $userId = $request->getAttribute('user_id');
            $params = $request->getQueryParams();
            $jobId = $params['jobId'] ?? $request->getAttribute('jobId');

            if (!$jobId) {
                return ResponseBuilder::badRequest(['message' => 'Job ID is required']);
            }

            $jobId = (int)$jobId;

            // Check if job exists
            $job = $this->jobRepository->findById($jobId);
            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            // Check if item is in wishlist
            if (!$this->wishlistRepository->isInWishlist($userId, $jobId)) {
                return ResponseBuilder::notFound(['message' => 'Job is not in wishlist']);
            }

            // Remove from wishlist
            $success = $this->wishlistRepository->removeFromWishlist($userId, $jobId);

            if ($success) {
                return ResponseBuilder::ok([
                    'message' => 'Job removed from wishlist successfully',
                    'job_id' => $jobId
                ]);
            } else {
                return ResponseBuilder::serverError(['message' => 'Failed to remove job from wishlist']);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get user's wishlist
     */
    public function getUserWishlist($request)
    {
        try {
            $userId = $request->getAttribute('user_id');
            
            // Get pagination parameters
            $page = (int)($request->getQueryParams()['page'] ?? 1);
            $limit = (int)($request->getQueryParams()['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // Get wishlist items
            $wishlistItems = $this->wishlistRepository->getWishlistByUser($userId, $limit, $offset);
            $totalCount = $this->wishlistRepository->getWishlistCount($userId);

            // Calculate pagination info
            $totalPages = ceil($totalCount / $limit);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            return ResponseBuilder::ok([
                'wishlist' => $wishlistItems,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next_page' => $hasNextPage,
                    'has_prev_page' => $hasPrevPage
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError(['message' => $e->getMessage()]);
        }
    }

    /**
     * Check if a job is in user's wishlist
     */
    public function isJobInWishlist($request)
    {
        try {
            $userId = $request->getAttribute('user_id');
            $params = $request->getQueryParams();
            $jobId = $params['jobId'] ?? $request->getAttribute('jobId');

            if (!$jobId) {
                return ResponseBuilder::badRequest(['message' => 'Job ID is required']);
            }

            $jobId = (int)$jobId;

            // Check if job exists
            $job = $this->jobRepository->findById($jobId);
            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            $isInWishlist = $this->wishlistRepository->isInWishlist($userId, $jobId);

            return ResponseBuilder::ok([
                'is_in_wishlist' => $isInWishlist,
                'job_id' => $jobId
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get user's wishlist job IDs
     */
    public function getUserWishlistIds($request)
    {
        try {
            $userId = $request->getAttribute('user_id');

            $jobIds = $this->wishlistRepository->getWishlistJobIds($userId);

            return ResponseBuilder::ok([
                'job_ids' => $jobIds,
                'count' => count($jobIds)
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError(['message' => $e->getMessage()]);
        }
    }

    /**
     * Toggle job in wishlist (add if not exists, remove if exists)
     */
    public function toggleWishlist($request)
    {
        try {
            $userId = $request->getAttribute('user_id');
            $data = json_decode($request->getBody()->getContents(), true);

            // Validate input
            $validationErrors = $this->validator->validate([
                'job_id' => $data['job_id'] ?? null
            ], [
                'job_id' => 'required|numeric|min:1'
            ]);

            if (!empty($validationErrors)) {
                return ResponseBuilder::unprocessableEntity([
                    'message' => 'Validation failed',
                    'errors' => $validationErrors
                ]);
            }

            $jobId = (int)$data['job_id'];

            // Check if job exists
            $job = $this->jobRepository->findById($jobId);
            if (!$job) {
                return ResponseBuilder::notFound(['message' => 'Job not found']);
            }

            // Check current state and toggle
            $isInWishlist = $this->wishlistRepository->isInWishlist($userId, $jobId);

            if ($isInWishlist) {
                // Remove from wishlist
                $success = $this->wishlistRepository->removeFromWishlist($userId, $jobId);
                $message = 'Job removed from wishlist';
                $action = 'removed';
            } else {
                // Add to wishlist
                $success = $this->wishlistRepository->addToWishlist($userId, $jobId);
                $message = 'Job added to wishlist';
                $action = 'added';
            }

            if ($success) {
                return ResponseBuilder::ok([
                    'message' => $message,
                    'job_id' => $jobId,
                    'action' => $action,
                    'is_in_wishlist' => !$isInWishlist
                ]);
            } else {
                return ResponseBuilder::serverError(['message' => 'Failed to update wishlist']);
            }
        } catch (\Exception $e) {
            return ResponseBuilder::serverError(['message' => $e->getMessage()]);
        }
    }
}