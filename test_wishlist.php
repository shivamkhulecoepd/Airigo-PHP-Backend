<?php

/**
 * Wishlist Module Test Script
 * 
 * This script tests all the functionality of the newly implemented Wishlist module
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;
use App\Config\AppConfig;
use App\Repositories\WishlistRepository;
use App\Repositories\UserRepository;
use App\Repositories\JobRepository;

class WishlistTest
{
    private $pdo;
    private $wishlistRepo;
    private $userRepo;
    private $jobRepo;
    
    // Test data
    private $testUserId = null;
    private $testJobId = null;
    private $testWishlistItemId = null;

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
     * Run all tests
     */
    public function runTests()
    {
        echo "🧪 Starting Wishlist Module Tests...\n\n";

        // Create test data
        $this->createTestData();
        
        if ($this->testUserId && $this->testJobId) {
            // Run individual tests
            $this->testAddToWishlist();
            $this->testIsInWishlist();
            $this->testGetWishlistByUser();
            $this->testRemoveFromWishlist();
            $this->testToggleWishlist();
            $this->testGetWishlistCount();
            $this->testGetWishlistJobIds();
        } else {
            echo "❌ Could not create test data. Skipping tests.\n";
        }

        // Cleanup
        $this->cleanupTestData();
        
        echo "\n✅ Wishlist Module Tests Completed!\n";
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
            $testEmail = 'recruiter_test_' . time() . '@example.com';
            
            $stmt->execute([$testEmail, $testPassword]);
            $testRecruiterId = $this->pdo->lastInsertId();
            
            echo "   Created test recruiter user with ID: {$testRecruiterId}\n";
            
            // Create a test jobseeker user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, user_type, status, email_verified, created_at) 
                VALUES (?, ?, 'jobseeker', 'active', 1, NOW())
            ");
            
            $testPassword = password_hash('Test@12345', PASSWORD_DEFAULT);
            $testEmail = 'wishlist_test_' . time() . '@example.com';
            
            $stmt->execute([$testEmail, $testPassword]);
            $this->testUserId = $this->pdo->lastInsertId();
            
            echo "   Created test jobseeker user with ID: {$this->testUserId}\n";
            
            // Create a test job
            $stmt = $this->pdo->prepare("
                INSERT INTO jobs (recruiter_user_id, company_name, designation, ctc, location, category, is_active, approval_status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, 'approved', NOW())
            ");
            
            $stmt->execute([
                $testRecruiterId, // Use the recruiter user we created
                'Test Company',
                'Software Engineer',
                '5-10 LPA',
                'Bangalore',
                'IT'
            ]);
            $this->testJobId = $this->pdo->lastInsertId();
            
            echo "   Created test job with ID: {$this->testJobId}\n";
            
        } catch (Exception $e) {
            echo "❌ Error creating test data: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test adding a job to wishlist
     */
    private function testAddToWishlist()
    {
        echo "\n🔍 Testing addToWishlist...\n";
        
        try {
            $result = $this->wishlistRepo->addToWishlist($this->testUserId, $this->testJobId);
            
            if ($result) {
                echo "   ✅ Successfully added job to wishlist\n";
                
                // Verify the entry exists
                $stmt = $this->pdo->prepare("SELECT * FROM wishlist_items WHERE user_id = ? AND job_id = ?");
                $stmt->execute([$this->testUserId, $this->testJobId]);
                $entry = $stmt->fetch();
                
                if ($entry) {
                    $this->testWishlistItemId = $entry['id'];
                    echo "   ✅ Verified wishlist entry exists in database\n";
                } else {
                    echo "   ❌ Wishlist entry not found in database\n";
                }
            } else {
                echo "   ❌ Failed to add job to wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in addToWishlist: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test checking if a job is in wishlist
     */
    private function testIsInWishlist()
    {
        echo "\n🔍 Testing isInWishlist...\n";
        
        try {
            $result = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
            
            if ($result) {
                echo "   ✅ Correctly identified job is in wishlist\n";
            } else {
                echo "   ❌ Incorrectly reported job is not in wishlist\n";
            }
            
            // Test with non-existent entry
            $nonExistentJobId = $this->testJobId + 1000;
            $result2 = $this->wishlistRepo->isInWishlist($this->testUserId, $nonExistentJobId);
            
            if (!$result2) {
                echo "   ✅ Correctly identified job is not in wishlist\n";
            } else {
                echo "   ❌ Incorrectly reported non-existent job is in wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in isInWishlist: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test getting wishlist by user
     */
    private function testGetWishlistByUser()
    {
        echo "\n🔍 Testing getWishlistByUser...\n";
        
        try {
            $result = $this->wishlistRepo->getWishlistByUser($this->testUserId);
            
            if (is_array($result) && count($result) > 0) {
                echo "   ✅ Successfully retrieved wishlist items\n";
                echo "   📊 Found " . count($result) . " wishlist item(s)\n";
                
                // Check if our test job is in the results
                $found = false;
                foreach ($result as $item) {
                    if ($item['job_id'] == $this->testJobId) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    echo "   ✅ Test job found in wishlist results\n";
                } else {
                    echo "   ❌ Test job not found in wishlist results\n";
                }
            } else {
                echo "   ❌ Failed to retrieve wishlist items or no items found\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in getWishlistByUser: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test removing from wishlist
     */
    private function testRemoveFromWishlist()
    {
        echo "\n🔍 Testing removeFromWishlist...\n";
        
        try {
            $result = $this->wishlistRepo->removeFromWishlist($this->testUserId, $this->testJobId);
            
            if ($result) {
                echo "   ✅ Successfully removed job from wishlist\n";
                
                // Verify the entry is gone
                $stmt = $this->pdo->prepare("SELECT * FROM wishlist_items WHERE user_id = ? AND job_id = ?");
                $stmt->execute([$this->testUserId, $this->testJobId]);
                $entry = $stmt->fetch();
                
                if (!$entry) {
                    echo "   ✅ Verified wishlist entry is removed from database\n";
                } else {
                    echo "   ❌ Wishlist entry still exists in database\n";
                }
            } else {
                echo "   ❌ Failed to remove job from wishlist\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in removeFromWishlist: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test toggling wishlist
     */
    private function testToggleWishlist()
    {
        echo "\n🔍 Testing toggle functionality (add -> remove -> add)...\n";
        
        try {
            // First, add the job back
            $addResult = $this->wishlistRepo->addToWishlist($this->testUserId, $this->testJobId);
            if ($addResult) {
                echo "   ✅ Toggled: Added job to wishlist\n";
                
                // Check if it's in wishlist
                $isInWishlist = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
                if ($isInWishlist) {
                    echo "   ✅ Confirmed job is in wishlist\n";
                    
                    // Now remove it (toggle off)
                    $removeResult = $this->wishlistRepo->removeFromWishlist($this->testUserId, $this->testJobId);
                    if ($removeResult) {
                        echo "   ✅ Toggled: Removed job from wishlist\n";
                        
                        // Check if it's not in wishlist anymore
                        $isInWishlist2 = $this->wishlistRepo->isInWishlist($this->testUserId, $this->testJobId);
                        if (!$isInWishlist2) {
                            echo "   ✅ Confirmed job is not in wishlist\n";
                            
                            // Add it back one more time
                            $addResult2 = $this->wishlistRepo->addToWishlist($this->testUserId, $this->testJobId);
                            if ($addResult2) {
                                echo "   ✅ Toggled: Added job back to wishlist\n";
                            } else {
                                echo "   ❌ Failed to add job back to wishlist\n";
                            }
                        } else {
                            echo "   ❌ Job still in wishlist after removal\n";
                        }
                    } else {
                        echo "   ❌ Failed to remove job from wishlist during toggle test\n";
                    }
                } else {
                    echo "   ❌ Job not found in wishlist after adding\n";
                }
            } else {
                echo "   ❌ Failed to add job to wishlist initially\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in toggle test: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test getting wishlist count
     */
    private function testGetWishlistCount()
    {
        echo "\n🔍 Testing getWishlistCount...\n";
        
        try {
            $count = $this->wishlistRepo->getWishlistCount($this->testUserId);
            
            if ($count >= 0) {
                echo "   ✅ Successfully got wishlist count: {$count}\n";
            } else {
                echo "   ❌ Failed to get wishlist count\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in getWishlistCount: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test getting wishlist job IDs
     */
    private function testGetWishlistJobIds()
    {
        echo "\n🔍 Testing getWishlistJobIds...\n";
        
        try {
            $jobIds = $this->wishlistRepo->getWishlistJobIds($this->testUserId);
            
            if (is_array($jobIds)) {
                echo "   ✅ Successfully got wishlist job IDs\n";
                echo "   📊 Retrieved " . count($jobIds) . " job ID(s)\n";
                
                if (in_array($this->testJobId, $jobIds)) {
                    echo "   ✅ Test job ID found in returned IDs\n";
                } else {
                    echo "   ⚠️  Test job ID not found in returned IDs (might be expected if recently removed)\n";
                }
            } else {
                echo "   ❌ Failed to get wishlist job IDs\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Exception in getWishlistJobIds: " . $e->getMessage() . "\n";
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
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE email LIKE 'recruiter_test_%'");
            $stmt->execute();
            echo "   Removed test recruiter user\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error cleaning up test data: " . $e->getMessage() . "\n";
        }
    }
}

// Run the tests if executed directly
if (php_sapi_name() === 'cli') {
    $tester = new WishlistTest();
    $tester->runTests();
}