<?php

namespace App\Core\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use App\Core\Utils\ResponseBuilder;
use App\Core\Utils\Validator;
use App\Repositories\IssueReportRepository;

class IssueReportController extends BaseController
{
    private IssueReportRepository $issueReportRepository;

    public function __construct()
    {
        parent::__construct();
        $this->issueReportRepository = new IssueReportRepository();
    }

    public function create(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $data = $this->getRequestBody($request);

        // Validate required fields
        $errors = $this->validateIssueReportData($data);
        if (!empty($errors)) {
            return ResponseBuilder::unprocessableEntity([
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
        }

        try {
            // Prepare issue report data
            $issueReportData = [
                'user_id' => $user['id'],
                'user_type' => $user['user_type'],
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => 'pending'
            ];

            $issueReportId = $this->issueReportRepository->create($issueReportData);

            if (!$issueReportId) {
                return ResponseBuilder::serverError([
                    'message' => 'Failed to create issue report'
                ]);
            }

            // Fetch the created issue report
            $issueReport = $this->issueReportRepository->findById($issueReportId);

            return ResponseBuilder::created([
                'message' => 'Issue report submitted successfully',
                'issue_report' => $issueReport
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to create issue report',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getAll(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $type = $this->getQueryParam($request, 'type');
        $status = $this->getQueryParam($request, 'status');
        $userType = $this->getQueryParam($request, 'user_type');

        $filters = [];
        if ($type) $filters['type'] = $type;
        if ($status) $filters['status'] = $status;
        if ($userType) $filters['user_type'] = $userType;

        try {
            $issueReports = $this->issueReportRepository->findAll($filters, $limit, ($page - 1) * $limit);
            $totalCount = $this->issueReportRepository->count($filters);

            return ResponseBuilder::ok([
                'issue_reports' => $issueReports,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch issue reports',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getMyReports(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $page = (int) $this->getQueryParam($request, 'page', 1);
        $limit = (int) $this->getQueryParam($request, 'limit', 10);
        $type = $this->getQueryParam($request, 'type');
        $status = $this->getQueryParam($request, 'status');

        $filters = ['user_id' => $user['id']];
        if ($type) $filters['type'] = $type;
        if ($status) $filters['status'] = $status;

        try {
            $issueReports = $this->issueReportRepository->findAll($filters, $limit, ($page - 1) * $limit);
            $totalCount = $this->issueReportRepository->count($filters);

            return ResponseBuilder::ok([
                'issue_reports' => $issueReports,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch issue reports',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getById(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        $issueReportId = (int) $request->getAttribute('id');

        if ($issueReportId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid issue report ID']);
        }

        try {
            $issueReport = $this->issueReportRepository->findById($issueReportId);

            if (!$issueReport) {
                return ResponseBuilder::notFound(['message' => 'Issue report not found']);
            }

            // Only allow the owner or admin to view the report
            if ($user['user_type'] !== 'admin' && $issueReport['user_id'] != $user['id']) {
                return ResponseBuilder::forbidden(['message' => 'Access denied']);
            }

            return ResponseBuilder::ok(['issue_report' => $issueReport]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to fetch issue report',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateStatus(ServerRequestInterface $request)
    {
        $user = $this->getUser($request);
        if (!$user) {
            return ResponseBuilder::unauthorized(['message' => 'User not authenticated']);
        }

        if ($user['user_type'] !== 'admin') {
            return ResponseBuilder::forbidden(['message' => 'Access denied. Admin privileges required.']);
        }

        $issueReportId = (int) $request->getAttribute('id');
        $data = $this->getRequestBody($request);
        $newStatus = $data['status'] ?? null;
        $adminResponse = $data['admin_response'] ?? null;

        if ($issueReportId <= 0) {
            return ResponseBuilder::badRequest(['message' => 'Invalid issue report ID']);
        }

        if (!$newStatus || !in_array($newStatus, ['pending', 'in_progress', 'resolved'])) {
            return ResponseBuilder::badRequest(['message' => 'Valid status is required: pending, in_progress, or resolved']);
        }

        try {
            $issueReport = $this->issueReportRepository->findById($issueReportId);

            if (!$issueReport) {
                return ResponseBuilder::notFound(['message' => 'Issue report not found']);
            }

            $updateData = [
                'status' => $newStatus
            ];

            if ($adminResponse !== null) {
                $updateData['admin_response'] = $adminResponse;
            }

            $result = $this->issueReportRepository->update($issueReportId, $updateData);

            if (!$result) {
                return ResponseBuilder::serverError(['message' => 'Failed to update issue report status']);
            }

            // Fetch updated issue report
            $updatedIssueReport = $this->issueReportRepository->findById($issueReportId);

            return ResponseBuilder::ok([
                'message' => 'Issue report status updated successfully',
                'issue_report' => $updatedIssueReport
            ]);
        } catch (\Exception $e) {
            return ResponseBuilder::serverError([
                'message' => 'Failed to update issue report status',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateIssueReportData(array $data): array
    {
        $errors = [];

        if (empty($data['type']) || !in_array($data['type'], ['issue', 'feedback'])) {
            $errors['type'] = 'Type is required and must be either "issue" or "feedback"';
        }

        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        } elseif (!$this->validator->isValidLength($data['title'], 1, 255)) {
            $errors['title'] = 'Title must be between 1 and 255 characters';
        }

        if (empty($data['description'])) {
            $errors['description'] = 'Description is required';
        } elseif (!$this->validator->isValidLength($data['description'], 1, 1000)) {
            $errors['description'] = 'Description must be between 1 and 1000 characters';
        }

        return $errors;
    }
}