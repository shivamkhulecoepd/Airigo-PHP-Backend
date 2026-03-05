<?php

namespace App\Config;

class AppConfig
{
    public static function get($key, $default = null)
    {
        $config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Airigo Job Portal',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
                'locale' => $_ENV['APP_LOCALE'] ?? 'en',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? '193.203.184.189',
                'port' => $_ENV['DB_PORT'] ?? 3306,
                'database' => $_ENV['DB_NAME'] ?? 'u233781988_airigoDB',
                'username' => $_ENV['DB_USER'] ?? 'u233781988_airigoDB',
                'password' => $_ENV['DB_PASSWORD'] ?? 'Airigo@#2026',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
                'prefix' => $_ENV['DB_PREFIX'] ?? '',
            ],
            'jwt' => [
                'secret' => $_ENV['JWT_SECRET_KEY'] ?? 'Airigo@2026',
                'refresh_secret' => $_ENV['JWT_REFRESH_SECRET_KEY'] ?? 'Airigo@2026',
                'expiry' => (int) ($_ENV['JWT_TOKEN_EXPIRY'] ?? 86400),
                'refresh_expiry' => (int) ($_ENV['JWT_REFRESH_TOKEN_EXPIRY'] ?? 604800),
            ],
            'firebase' => [
                'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? 'airigo-jobs',
                'private_key_id' => $_ENV['FIREBASE_PRIVATE_KEY_ID'] ?? '6492aa3c155e7546bcad7528c80425d69481ba75',
                'private_key' => $_ENV['FIREBASE_PRIVATE_KEY'] ?? '-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCbzCNMQst3RF/m\nUrmZkx82xdWLlr7vLSeoV7UkuIVw21JyaG1lLhYhKlZ66nZ7EpXA+x5ivGOJPFnp\nj+0+2LxEMMs9yd5DpNMpA8yj7X1/bylgeTVEKZ1JtN7WzqwB3pXalQQEqCGlFWrC\nJRSYetRGB3/wwk49jVq03ahlRvL53AiH7bL7g3wdzBu43FX4byl/IvLi2pLa5YzN\nNXs9BaDYaNEfa8wubsEs3WJ4fQC4MbalIGnOgj6vGIYgzHunS5pLeGA3q8dmF462\njptcMZ1ZOgfWU4GeBMwKdLiWvQbJ9lTLzsTNVxZv9yvDmMcwkxPvyq0eWcMynX+d\nfEjxXxzlAgMBAAECggEABBl8ikqtqO9DKL/fg2OELEBLbLdkQc3p37ujGBrE5CHZ\nSwplaoaqbWf8S5K02Gs2RpQ1CfsUEW3lncg8QW2Zkp/IEVGpCBWjus4PSfb7WOmX\naKL33FnN+j0IqhVZIFl3jv9h0i+MZxgnrYElSBffUxJqcu7h+Supid3VSwjxZtKx\nYh+KGvuvPsQtT6Api7f1pkSyyp7TVlF30RtaBDmSKjsxxxmkmZQx7PXpgJqdeEj0\nDjJZPVmZl3XDdNn7M/DvonUZRlOSvsSGQ1v84Xi4Pci9KRtDbLjIY15rz+RnIluP\nKccVblQfcyA/bIil6mjBlZH1hNZSAM7vMLM8v2X2BwKBgQDIlBMkMdNZj/vQBpJo\njL+jTXommirnBtvm8Aqu1s/Qw9q/Oe0T4fkg+h98ggOrcZcgB0GW5PEJHbfiBsqr\nJaryzZaErExaeS07RVbnPnhTAhdSTjC9vdJEwYgdv1iHn6fEsyClVNVXODRUyMsM\npzN//jmeLKPCJx8BLAAggX/IVwKBgQDG2Hn1NtQ/lT9l7U0X+AsP/rCVKX6de0tR\nA6omMw59f3WAE3OhepVRpUtNIeC3fVGTQRqogDE+6Ufcyr6iIKt60w6nmTN5WDQs\ntuCHSmRYl0S2TAZ7I1b00U2D3AlrYgVM35Cf91kt2Xd+nGElVkgMu4ZbWfImSVUT\nsEojcZ9vIwKBgGShZbkTBmY4xq1nnqy1cLANfus/Dac62bjTVYjCXSDwIh8ugLMo\n/ER/OKzOzeiF5Lw857s8wXFBZ7AOmD+ldk66tnl5uBTsFrVV5HO/874xnmG8uNd5\nFLVKI3BJP7FLeHBHLmnEVgScPiULWFPQzxW4BlBFNSODXRrJaIbmcaWhAoGBALMu\nfzeooLprEyYWIFJpAg73wsenDKF8aPIoCztA5t3P7WHsJVZt0AAyoxhuXsD5/Hhl\nQlB0s+us60Tarc4LAns7lQkR1ICUKu/gG5PORX5PUWu0NmLgBYu2z9LyhMpvGbeb\n/gcoLQRT4ooFAMVUariOgxPuiXZWvoNvaF9oE/NhAoGAFAeLdofuX7usPe4Dmrhu\nXxHVg8xv831wZVyD5NMTuoSW60C7mQAaAnYQRZiX2UMEKJ4VXmeo9Gbhq9gBcWG0\nfnqUgGd02n3ClUKA/5jdGAmqaJYnZYOscKDeTttL6/JKaxh0o4EnBNxqr6WAcf6d\nzjSMEUzf/xNfcBebiyMbOvQ=\n-----END PRIVATE KEY-----\n',
                'client_email' => $_ENV['FIREBASE_CLIENT_EMAIL'] ?? 'firebase-adminsdk-fbsvc@airigo-jobs.iam.gserviceaccount.com',
                'client_id' => $_ENV['FIREBASE_CLIENT_ID'] ?? '101278806988782075893',
                'auth_uri' => $_ENV['FIREBASE_AUTH_URI'] ?? 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => $_ENV['FIREBASE_TOKEN_URI'] ?? 'https://oauth2.googleapis.com/token',
                'storage_bucket' => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? 'airigo-jobs.firebasestorage.app',
            ],
            'redis' => [
                'host' => $_ENV['REDIS_HOST'] ?? 'redis-16020.crce281.ap-south-1-3.ec2.cloud.redislabs.com',
                'port' => (int) ($_ENV['REDIS_PORT'] ?? 16020),
                'password' => $_ENV['REDIS_PASSWORD'] ?? 'H7ShCq9UPNRHYrQhkqxztFnvXmabtBN8',
                "username" => $_ENV["REDIS_USERNAME"] ?? 'default',
                'database' => (int) ($_ENV['REDIS_DATABASE'] ?? 14052033),
            ]
        ];

        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
