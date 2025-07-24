<?php

class APIErrorHandler {
    private static $errorMessages = [
        400 => 'Bad Request - Invalid request format or parameters',
        401 => 'Unauthorized - Invalid or missing API key',
        403 => 'Forbidden - Access denied',
        404 => 'Not Found - Resource does not exist',
        413 => 'Request Too Large - Request size exceeds 100KB limit',
        429 => 'Too Many Requests - Rate limit exceeded (max 10 requests/second)',
        500 => 'Internal Server Error - Something went wrong on our end',
        555 => 'Random API Error - This is expected behavior for testing resilience',
    ];

    public static function handleResponse($response, $httpCode = 200) {
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $response
            ];
        }

        $errorMessage = self::getErrorMessage($httpCode, $response);
        $retryAfter = self::getRetryAfter($response, $httpCode);

        error_log("API Error [{$httpCode}]: {$errorMessage}");

        return [
            'success' => false,
            'error' => $errorMessage,
            'http_code' => $httpCode,
            'retry_after' => $retryAfter,
            'raw_response' => $response
        ];
    }

    private static function getErrorMessage($httpCode, $response) {
        if (is_array($response) && isset($response['error'])) {
            return $response['error'];
        }

        if (isset(self::$errorMessages[$httpCode])) {
            return self::$errorMessages[$httpCode];
        }

        return "HTTP {$httpCode}: An unexpected error occurred";
    }

    private static function getRetryAfter($response, $httpCode) {
        if ($httpCode === 429 && is_array($response) && isset($response['retryAfter'])) {
            return $response['retryAfter'];
        }
        return null;
    }

    public static function displayErrorPage($errorData, $returnUrl = null) {
        $httpCode = $errorData['http_code'] ?? 500;
        $errorMessage = $errorData['error'] ?? 'Unknown error occurred';
        $retryAfter = $errorData['retry_after'] ?? null;
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Error <?php echo $httpCode; ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                }
                .error-code {
                    font-size: 4rem;
                    font-weight: bold;
                    color: #e74c3c;
                    margin: 0;
                }
                .error-message {
                    font-size: 1.2rem;
                    color: #555;
                    margin: 20px 0 30px;
                    line-height: 1.5;
                }
                .retry-info {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    margin: 10px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }
                .btn-primary {
                    background: #3498db;
                    color: white;
                }
                .btn-primary:hover {
                    background: #2980b9;
                }
                .btn-secondary {
                    background: #95a5a6;
                    color: white;
                }
                .btn-secondary:hover {
                    background: #7f8c8d;
                }
                .error-details {
                    text-align: left;
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin-top: 20px;
                    font-family: monospace;
                    font-size: 0.9rem;
                    display: none;
                }
                .toggle-details {
                    color: #3498db;
                    cursor: pointer;
                    text-decoration: underline;
                    font-size: 0.9rem;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1 class="error-code"><?php echo $httpCode; ?></h1>
                <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
                
                <?php if ($retryAfter): ?>
                <div class="retry-info">
                    <strong>Rate Limited:</strong> Please wait <?php echo $retryAfter; ?> seconds before retrying.
                </div>
                <?php endif; ?>

                <?php if ($httpCode === 429): ?>
                <div class="retry-info">
                    <strong>Tip:</strong> Limit your requests to 10 per second to avoid rate limiting.
                </div>
                <?php endif; ?>

                <?php if ($httpCode === 555): ?>
                <div class="retry-info">
                    <strong>Note:</strong> This is a random test error. Your app should handle this gracefully and retry.
                </div>
                <?php endif; ?>

                <div>
                    <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
                    <?php if ($returnUrl): ?>
                    <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-secondary">Return to Home</a>
                    <?php endif; ?>
                    <a href="javascript:location.reload()" class="btn btn-secondary">Try Again</a>
                </div>

                <div style="margin-top: 20px;">
                    <span class="toggle-details" onclick="toggleDetails()">Show Error Details</span>
                </div>

                <div id="errorDetails" class="error-details">
                    <strong>HTTP Code:</strong> <?php echo $httpCode; ?><br>
                    <strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                    <?php if ($retryAfter): ?>
                    <strong>Retry After:</strong> <?php echo $retryAfter; ?> seconds<br>
                    <?php endif; ?>
                    <strong>Raw Response:</strong><br>
                    <pre><?php echo htmlspecialchars(json_encode($errorData['raw_response'], JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>

            <script>
                function toggleDetails() {
                    const details = document.getElementById('errorDetails');
                    const toggle = document.querySelector('.toggle-details');
                    
                    if (details.style.display === 'none' || details.style.display === '') {
                        details.style.display = 'block';
                        toggle.textContent = 'Hide Error Details';
                    } else {
                        details.style.display = 'none';
                        toggle.textContent = 'Show Error Details';
                    }
                }

                let countdown = 3;
                const retryBtn = document.querySelector('[href="javascript:location.reload()"]');
                const originalText = retryBtn.textContent;
                
                const timer = setInterval(() => {
                    countdown--;
                    retryBtn.textContent = `Auto-retry in ${countdown}s`;
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        location.reload();
                    }
                }, 1000);
                <?php endif; ?>
            </script>
        </body>
        </html>
        <?php
    }

    public static function checkAndHandleError($response, $httpCode = 200, $returnUrl = null) {
        $result = self::handleResponse($response, $httpCode);
        
        if (!$result['success']) {
            self::displayErrorPage($result, $returnUrl);
            exit;
        }
        
        return $result['data'];
    }

    public static function makeRequestWithErrorHandling($apiInstance, $method, $endpoint, $data = null, $query = [], $autoHandle = true, $returnUrl = null) {
        $url = $apiInstance->baseUrl . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiInstance->apiKey}"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($autoHandle) {
            return self::checkAndHandleError($decodedResponse, $httpCode, $returnUrl);
        } else {
            return self::handleResponse($decodedResponse, $httpCode);
        }
    }
}

function handleApiError($response, $httpCode = 200, $returnUrl = null) {
    return APIErrorHandler::checkAndHandleError($response, $httpCode, $returnUrl);
}

function showErrorPage($httpCode, $message, $retryAfter = null, $returnUrl = null) {
    $errorData = [
        'success' => false,
        'error' => $message,
        'http_code' => $httpCode,
        'retry_after' => $retryAfter,
        'raw_response' => ['error' => $message]
    ];
    
    APIErrorHandler::displayErrorPage($errorData, $returnUrl);
    exit;
}

?>