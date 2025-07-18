<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>qOverflow API - Error Codes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .error-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .error-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .error-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .error-400 { border-left-color: #e74c3c; }
        .error-401 { border-left-color: #f39c12; }
        .error-403 { border-left-color: #e67e22; }
        .error-404 { border-left-color: #9b59b6; }
        .error-500 { border-left-color: #c0392b; }
        .error-503 { border-left-color: #34495e; }

        .error-code {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .error-400 .error-code { color: #e74c3c; }
        .error-401 .error-code { color: #f39c12; }
        .error-403 .error-code { color: #e67e22; }
        .error-404 .error-code { color: #9b59b6; }
        .error-500 .error-code { color: #c0392b; }
        .error-503 .error-code { color: #34495e; }

        .error-title {
            font-size: 1.4em;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .error-description {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .error-examples {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .error-examples h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .error-examples ul {
            list-style: none;
            padding-left: 0;
        }

        .error-examples li {
            color: #5a6c7d;
            margin-bottom: 5px;
            position: relative;
            padding-left: 20px;
        }

        .error-examples li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #bdc3c7;
        }

        .intro {
            text-align: center;
            margin-bottom: 20px;
        }

        .intro h2 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 15px;
        }

        .intro p {
            color: #7f8c8d;
            font-size: 1.1em;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
        }

        .api-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }

        .api-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .api-info p {
            color: #5a6c7d;
            margin-bottom: 5px;
        }

        .api-info code {
            background: #e8f4f8;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #2c3e50;
        }

        .active-error {
            transform: scale(1.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border-left-width: 8px;
        }

        .error-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .error-message h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 1.3em;
        }

        .error-message p {
            color: #856404;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .error-message .solution {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }

        .error-message .solution h4 {
            color: #155724;
            margin-bottom: 8px;
        }

        .error-message .solution p {
            color: #155724;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .error-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>qOverflow API</h1>
            <p>Error Code Reference</p>
        </div>
        
        <div class="content">
            <div class="intro">
                <h2>HTTP Error Codes</h2>
                <p>Understanding the error responses from the qOverflow API will help you build more robust applications. Each error code provides specific information about what went wrong and how to resolve it.</p>
            </div>

            <div class="api-info">
                <h3>API Information</h3>
                <p><strong>Base URL:</strong> <code>https://qoverflow.api.hscc.bdpa.org/v1</code></p>
                <p><strong>Authentication:</strong> Bearer Token required in Authorization header</p>
                <p><strong>Content-Type:</strong> <code>application/json</code></p>
            </div>

            <div class="error-grid">
                <div class="error-card error-400">
                    <div class="error-code">400</div>
                    <div class="error-title">Bad Request</div>
                    <div class="error-description">
                        The server cannot process the request due to invalid syntax or missing required parameters. This typically occurs when the request body is malformed or contains invalid data.
                    </div>
                    <div class="error-examples">
                        <h4>Common Causes:</h4>
                        <ul>
                            <li>Missing required fields (username, email, etc.)</li>
                            <li>Invalid JSON format in request body</li>
                            <li>Field validation errors (invalid email format)</li>
                            <li>Invalid parameter values</li>
                        </ul>
                    </div>
                </div>

                <div class="error-card error-401">
                    <div class="error-code">401</div>
                    <div class="error-title">Unauthorized</div>
                    <div class="error-description">
                        Authentication is required but was not provided or is invalid. This error occurs when the API key is missing, expired, or incorrect.
                    </div>
                    <div class="error-examples">
                        <h4>Common Causes:</h4>
                        <ul>
                            <li>Missing Authorization header</li>
                            <li>Invalid or expired API key</li>
                            <li>Incorrect Bearer token format</li>
                            <li>API key lacks required permissions</li>
                        </ul>
                    </div>
                </div>

                <div class="error-card error-403">
                    <div class="error-code">403</div>
                    <div class="error-title">Forbidden</div>
                    <div class="error-description">
                        The request is understood but access is denied. You may be authenticated but lack the necessary permissions to perform the requested action.
                    </div>
                    <div class="error-examples">
                        <h4>Common Causes:</h4>
                        <ul>
                            <li>Insufficient permissions for the operation</li>
                            <li>Trying to modify another user's data</li>
                            <li>Account limitations or restrictions</li>
                            <li>Rate limiting or quota exceeded</li>
                        </ul>
                    </div>
                </div>

                <div class="error-card error-404">
                    <div class="error-code">404</div>
                    <div class="error-title">Not Found</div>
                    <div class="error-description">
                        The requested resource could not be found. This can happen when trying to access non-existent users, questions, answers, or comments.
                    </div>
                    <div class="error-examples">
                        <h4>Common Causes:</h4>
                        <ul>
                            <li>User does not exist</li>
                            <li>Question or answer ID not found</li>
                            <li>Invalid endpoint URL</li>
                            <li>Resource was deleted</li>
                        </ul>
                    </div>
                </div>

                <div class="error-card error-500">
                    <div class="error-code">500</div>
                    <div class="error-title">Internal Server Error</div>
                    <div class="error-description">
                        An unexpected error occurred on the server. This is usually a temporary issue with the API infrastructure and should be retried after a brief delay.
                    </div>
                    <div class="error-examples">
                        <h4>Common Causes:</h4>
                        <ul>
                            <li>Database connection issues</li>
                            <li>Server overload or maintenance</li>
                            <li>Unexpected application errors</li>
                            <li>Third-party service failures</li>
                        </ul>
                    </div>
                </div>

                <div class="error-card error-503">
                    <div class="error-code">503</div>
                    <div class="error-title">Service Unavailable</div>
                    <div class="error-description">
                        The server is temporarily unavailable, typically due to maintenance or overload. The service should be available again shortly.
                    </div>
                    <div class="error-examples">
                        <h4>Common Causes:</h4>
                        <ul>
                            <li>Scheduled maintenance</li>
                            <li>Server overload</li>
                            <li>Database maintenance</li>
                            <li>Temporary service disruption</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>