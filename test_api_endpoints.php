<?php

/**
 * Wishlist API Endpoints Test Script
 * 
 * This script tests the API endpoints for the Wishlist module
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;
use App\Config\AppConfig;
use App\Repositories\WishlistRepository;
use App\Repositories\UserRepository;
use App\Repositories\JobRepository;

class WishlistAPITest
{
    private $pdo;
    private $wishlistRepo;
    private $userRepo;
    private $jobRepo;
    
    // Test data
    private $testUserId = null;
    private $testJobId = null;
    private $testRecruiterId = null;

    public function __construct()
    {
        try {
            $this->pdo = Connection::getInstance();
            $this->wishlistRepo = new WishlistRepository();
            $this->userRepo = new UserRepository();
            $this->jobRepo = new JobRepository();
        } catch (Exception $e) {
            die("❌ Could not connect to database: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Run API endpoint tests
     */
    public function runTests()
    {
        echo "🧪 Starting Wishlist API Endpoint Tests...\n\n";

        // Create test data
        $this->createTestData();
        
        if ($this->testUserId && $this->testJobId) {
            // Test each API functionality by calling the repository methods directly
            // (simulating what the controller would do)
            $this->testAddToWishlistAPI();
            $this->testRemoveFromWishlistAPI();
            $this->testGetUserWishlistAPI();
            $this->testIsJobInWishlistAPI();
            $this->testGetUserWishlistIdsAPI();
            $this->testToggleWishlistAPI();
        } else {
            echo "❌ Could not create test data. Skipping tests.\n";
        }

        // Cleanup
        $this->cleanupTestData();
        
        echo "\n✅ Wishlist API Endpoint Tests Completed!\n";
    }

    /**
     * Create test data for testing
     */
    private function createTestData()
    {
        echo "📝 Creating test data...\n";
        
        try {
            // Create a test recruiter user first (needed for job creation)
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, user_type, status, email_verified, created_at) 
                VALUES (?, ?, 'recruiter', 'active', 1, NOW())
            ");
            
            $testPassword = password_hash('Test@12345', PASSWORD_DEFAULT);
            $testEmail = 'recruiter_api_test_' . time() . '@example.com';
            
            $stmt->execute([$testEmail, $testPassword]);
            $this->testRecruiterId = $this->pdo->lastInsertId();
            
            echo "   Created test recruiter user with ID: {$this->testRecruiterId}\n";
            
            // Create a test jobseeker user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, user_type, status, email_verified, created_at) 
                VALUES (?, ?, 'jobseeker', 'active', 1, NOW())
            ");
            
            $testPassword = password_hash('Test@12345', PASSWORD_DEFAULT);
            $testEmail = 'api_test_' . time() . '@example.com';
            
            $stmt->execute([$testEmail, $testPassword]);
            $this->testUserId = $this->pdo->lastInsertId();
            
            echo "   Created test jobseeker user with ID: {$this->testUserId}\n";
            
            // Create a test job
            $stmt = $this->pdo->prepare("
                INSERT INTO jobs (recruiter_user_id, company_name, designation, ctc, location, category, is_active, approval_status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, 'approved', NOW())
            ");
            
            $stmt->execute([
                $this->testRecruiterId, // Use the recruiter user we created
                'Test API Company',
                'API Test Engineer',
                '8-12 LPA',
                'Mumbai',
                'Technology'
            ]);
            $this->testJobId = $this->pdo->lastInsertId();
            
            echo "   Created test job with ID: {$this->testJobId}\n";
            
        } catch (Exception $e) {
            echo "❌ Error creating test data: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Simulate POST /api/wishlist (addToWishlist)
     */
    private function testAddToWishlistAPI()
    {
        echo "\n🔗 Testing ADD to wishlist API endpoint simulation...\n";
        
        // Simulate what the controller would receive
        $requestData = [
            'job_id' => $this->testJobId
        ];
        
        try {
            // This simulates the controller logic
            $validationErrors = []; // Assume validation passed
            
            if (empty($validationErrors)) {
                $success = $this->wishlistRepo->addToWishlist($this->testUserId, $this->testJobId);
                
                if ($success) {
                    echo "   ✅ API simulation: Successfully added job to wishlist\n";
                } else {
                    echo "   ❌ API simulation: Failed to add job to wishlist\n";
                }
            } else {
                echo "   ❌ API simulation: Validation failed\n";
            }
        } catch (Exception $e) {
            echo "   ❌ API simulation: Exception in add to wishlist - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Simulate GET /api/wishlist (getUserWishlist)
     */
    private function testGetUserWishlistAPI()
    {
        echo "\n🔗 Testing GET user wishlist API endpoint simulation...\n";
        
        try {
            // Simulate pagination params
            $page = 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $wishlistItems = $this->wishlistRepo->getWishlistByUser($this->testUserId, $limit, $offset);
            $totalCount = $this->wishlistRepo->getWishlistCount($this->testUserId);

            if (is_array($wishlistItems)) {
                echo "   ✅ API simulation: Successfully retrieved user wishlist\n";
                echo "   📊 Found {$totalCount} items in wishlist\n";
                echo "   📄 Returned " . count($wishlistItems) . " items in current page\n";
                
                // Check if our test job is in the results
                $found = false;
                foreach ($wishlistItems as $item) {
                    if ($item['job_id'] == $this->testJobId) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    echo "   ✅ API simulation: Test job found in wishlist results\n";
                } else {
                    echo "   ⚠️  API simulation: Test job not found in wishlist results\n";
                }
            } else {
                echo "   ❌ API simulation: Failed to retrieve user wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ API simulation: Exception in get wishlist - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Simulate GET /api/wishlist/check/{jobId} (isJobInWishlist)
     */
    private function testIsJobInWishlistAPI()
    {
        echo "\n🔗 Testing CHECK wishlist status API endpoint simulation...\n";
        
        try {
            $isInWishlist = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
            
            if ($isInWishlist) {
                echo "   ✅ API simulation: Correctly identified job is in wishlist\n";
            } else {
                echo "   ❌ API simulation: Incorrectly reported job is not in wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ API simulation: Exception in check wishlist - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Simulate GET /api/wishlist/ids (getUserWishlistIds)
     */
    private function testGetUserWishlistIdsAPI()
    {
        echo "\n🔗 Testing GET wishlist IDs API endpoint simulation...\n";
        
        try {
            $jobIds = $this->wishlistRepo->getWishlistJobIds($this->testUserId);

            if (is_array($jobIds)) {
                echo "   ✅ API simulation: Successfully retrieved wishlist IDs\n";
                echo "   📊 Retrieved " . count($jobIds) . " wishlist job IDs\n";
                
                if (in_array($this->testJobId, $jobIds)) {
                    echo "   ✅ API simulation: Test job ID found in returned IDs\n";
                } else {
                    echo "   ⚠️  API simulation: Test job ID not found in returned IDs\n";
                }
            } else {
                echo "   ❌ API simulation: Failed to retrieve wishlist IDs\n";
            }
        } catch (Exception $e) {
            echo "   ❌ API simulation: Exception in get wishlist IDs - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Simulate POST /api/wishlist/toggle (toggleWishlist)
     */
    private function testToggleWishlistAPI()
    {
        echo "\n🔗 Testing TOGGLE wishlist API endpoint simulation...\n";
        
        try {
            // First, check current state
            $isInWishlist = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
            
            if ($isInWishlist) {
                // Remove from wishlist
                $success = $this->wishlistRepo->removeFromWishlist($this->testUserId, $this->testJobId);
                $expectedAction = 'removed';
            } else {
                // Add to wishlist
                $success = $this->wishlistRepo->addToWishlist($this->testUserId, $this->testJobId);
                $expectedAction = 'added';
            }
            
            if ($success) {
                echo "   ✅ API simulation: Successfully toggled wishlist ({$expectedAction})\n";
                
                // Verify the toggle worked
                $newState = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
                $expectedState = $expectedAction === 'added';
                
                if ($newState === $expectedState) {
                    echo "   ✅ API simulation: Toggle state verified correctly\n";
                } else {
                    echo "   ❌ API simulation: Toggle state verification failed\n";
                }
            } else {
                echo "   ❌ API simulation: Failed to toggle wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ API simulation: Exception in toggle wishlist - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Simulate DELETE /api/wishlist (removeFromWishlist)
     */
    private function testRemoveFromWishlistAPI()
    {
        echo "\n🔗 Testing REMOVE from wishlist API endpoint simulation...\n";
        
        // First, make sure the job is in the wishlist
        $this->wishlistRepo->addToWishlist($this->testUserId, $this->testJobId);
        
        try {
            $success = $this->wishlistRepo->removeFromWishlist($this->testUserId, $this->testJobId);
            
            if ($success) {
                echo "   ✅ API simulation: Successfully removed job from wishlist\n";
                
                // Verify it's really removed
                $isInWishlist = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
                
                if (!$isInWishlist) {
                    echo "   ✅ API simulation: Verified job is no longer in wishlist\n";
                } else {
                    echo "   ❌ API simulation: Job still appears to be in wishlist after removal\n";
                }
            } else {
                echo "   ❌ API simulation: Failed to remove job from wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ API simulation: Exception in remove from wishlist - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Cleanup test data
     */
    private function cleanupTestData()
    {
        echo "\n🧹 Cleaning up test data...\n";
        
        try {
            // Remove wishlist entries for test user
            $stmt = $this->pdo->prepare("DELETE FROM wishlist_items WHERE user_id = ?");
            $stmt->execute([$this->testUserId]);
            echo "   Removed wishlist entries for test user\n";
            
            // Remove test job
            $stmt = $this->pdo->prepare("DELETE FROM jobs WHERE id = ?");
            $stmt->execute([$this->testJobId]);
            echo "   Removed test job\n";
            
            // Remove test jobseeker user
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$this->testUserId]);
            echo "   Removed test jobseeker user\n";
            
            // Remove test recruiter user
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$this->testRecruiterId]);
            echo "   Removed test recruiter user\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error cleaning up test data: " . $e->getMessage() . "\n";
        }
    }
}

// Run the tests if executed directly
if (php_sapi_name() === 'cli') {
    $tester = new WishlistAPITest();
    $tester->runTests();
}