<?php

namespace App\Core\Utils;

class Validator
{
    /**
     * Validate email format
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number format
     */
    public function isValidPhone(string $phone): bool
    {
        // Basic phone validation - allows digits, spaces, hyphens, parentheses, plus sign
        return preg_match('/^[+]?[\d\s\-\(\)]{10,}$/', $phone) === 1;
    }

    /**
     * Validate password strength
     */
    public function isValidPassword(string $password): bool
    {
        // At least 8 characters, with at least one uppercase, lowercase, and number
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    /**
     * Validate if value is numeric
     */
    public function isNumeric($value): bool
    {
        return is_numeric($value);
    }

    /**
     * Validate if value is boolean
     */
    public function isBoolean($value): bool
    {
        return is_bool($value) || $value === 'true' || $value === 'false' || $value === '1' || $value === '0';
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    public function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate if value is in allowed values
     */
    public function isIn($value, array $allowedValues): bool
    {
        return in_array($value, $allowedValues, true);
    }

    /**
     * Validate string length
     */
    public function isValidLength(string $value, int $min = null, int $max = null): bool
    {
        $length = strlen($value);
        
        if ($min !== null && $length < $min) {
            return false;
        }
        
        if ($max !== null && $length > $max) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate required field
     */
    public function isRequired($value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * Validate URL format
     */
    public function isValidUrl(string $url): bool
    {
        // Trim whitespace
        $url = trim($url);
        
        // Check if empty
        if (empty($url)) {
            return false;
        }
        
        // Check for valid URL characters
        if (!preg_match('/^[a-zA-Z0-9:\/\-_\.%~\?#\+=&@!*\(\)\[\];,]+$/', $url)) {
            return false;
        }
        
        // Add protocol if missing for validation
        $fullUrl = $url;
        if (!preg_match('/^https?:\/\//', $url)) {
            $fullUrl = 'http://' . $url;
        }
        
        // Use filter_var to validate the full URL
        return filter_var($fullUrl, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate JSON string
     */
    public function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate file upload
     */
    public function isValidFileUpload(array $file, array $allowedTypes = [], int $maxSize = null): bool
    {
        if (!isset($file['tmp_name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($maxSize && $file['size'] > $maxSize) {
            return false;
        }

        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run validation rules on data
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($rulesArray as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

                $value = $data[$field] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if (!$this->isRequired($value)) {
                            $errors[$field][] = "$field is required";
                        }
                        break;
                    
                    case 'email':
                        if ($value !== null && $value !== '' && !$this->isValidEmail($value)) {
                            $errors[$field][] = "$field must be a valid email";
                        }
                        break;
                        
                    case 'numeric':
                        if ($value !== null && $value !== '' && !$this->isNumeric($value)) {
                            $errors[$field][] = "$field must be numeric";
                        }
                        break;
                        
                    case 'boolean':
                        if ($value !== null && $value !== '' && !$this->isBoolean($value)) {
                            $errors[$field][] = "$field must be a boolean";
                        }
                        break;
                        
                    case 'date':
                        if ($value !== null && $value !== '' && !$this->isValidDate($value)) {
                            $errors[$field][] = "$field must be a valid date (YYYY-MM-DD)";
                        }
                        break;
                        
                    case 'in':
                        if ($value !== null && $value !== '' && !$this->isIn($value, $ruleParams)) {
                            $errors[$field][] = "$field must be one of: " . implode(', ', $ruleParams);
                        }
                        break;
                        
                    case 'min':
                        if ($value !== null && $value !== '' && strlen($value) < (int)$ruleParams[0]) {
                            $errors[$field][] = "$field must be at least " . (int)$ruleParams[0] . " characters";
                        }
                        break;
                        
                    case 'max':
                        if ($value !== null && $value !== '' && strlen($value) > (int)$ruleParams[0]) {
                            $errors[$field][] = "$field must not exceed " . (int)$ruleParams[0] . " characters";
                        }
                        break;
                        
                    case 'between':
                        if ($value !== null && $value !== '') {
                            $min = (int)$ruleParams[0];
                            $max = (int)$ruleParams[1];
                            $length = strlen($value);
                            
                            if ($length < $min || $length > $max) {
                                $errors[$field][] = "$field must be between $min and $max characters";
                            }
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}