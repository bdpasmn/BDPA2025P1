<?php 

header('Content-Type: text/html; charset=UTF-8');

// Retrieve error code from URL query string or use default
$errorCode = isset($_GET['code']) ? htmlspecialchars($_GET['code'], ENT_QUOTES, 'UTF-8') : 'Uh oh...';

// Check if no error code is provided or if it's empty - redirect to index.php
if (!isset($_GET['code']) || empty($_GET['code']) || $_GET['code'] === '') {
    header('Location: ../index.php');
    exit();
}

// Define valid error codes
$validErrorCodes = array(
    '400', '401', '403', '404', '409', '413', '422', '429', 
    '500', '502', '503', '504', '999', 'Uh oh...'
);

// Check if the error code is not in our valid list and not a 5xx server error
if (!in_array($errorCode, $validErrorCodes) && !is_numeric($errorCode)) {
    header('Location: index.php');
    exit();
}

// If it's a numeric code, check if it's a valid HTTP error code range
if (is_numeric($errorCode)) {
    $numericCode = intval($errorCode);
    // Only allow common error codes (4xx client errors and 5xx server errors)
    if ($numericCode < 400 || ($numericCode > 599)) {
        header('Location: index.php');
        exit();
    }
}

//echo 'error code is ' . $errorCode;
// Default error message
$errorMessage = 'Something went wrong with qOverflow. Please try again later';

//  error codes to user-friendly messages 
$errorMessages = array(
  //  '400' => 'Your qOverflow request has a problem. Please check your input and try again.',
    '401' => 'You need to authenticate to access qOverflow resources.',
    '403' => 'You don\'t have permission to perform this qOverflow action.',
    '404' => 'The qOverflow resource you\'re looking for isn\'t available.',
    '409' => 'There\'s a conflict with your qOverflow request. The resource may already exist.',
    '413' => 'Your qOverflow request is too large.',
    '422' => 'Your qOverflow request data is invalid. Please check your input.',
    '429' => 'Too many qOverflow requests. Please wait a moment and try again.',
    '500' => 'qOverflow server error. Please try again later.',
    '502' => 'qOverflow service is temporarily unavailable.',
    '503' => 'qOverflow service is temporarily down for maintenance.',
    '504' => 'qOverflow request timed out. Please try again.',
    '999' =>  'Something is wrong with our page come back later',
    'Uh oh...' => 'Something went wrong with qOverflow. Please try again later.',
);

if (is_numeric($errorCode) && $errorCode >= 500 && $errorCode <= 529) {
    $displayMessage = 'Something happened on the qOverflow server that is outside of our control. Please try again later.';
} else {
    error_log("qOverflow API Error Code: " . $errorCode);
    $displayMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : $errorMessage;
    error_log("qOverflow Display Message: " . $displayMessage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>qOverflow Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .error-icon-filter {
            filter: brightness(0) invert(1);
        }
        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .text-shadow-sm {
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body class="bg-gray-900 m-0 p-0 font-sans text-white flex justify-start items-center min-h-screen text-left">
    <div class="absolute top-5 left-5 text-white text-lg font-bold text-shadow-sm">qOverflow</div>
    
    <div class="max-w-4xl w-full px-5">
        <div class="flex items-center justify-start flex-row mb-5 ml-7">
            <div class="mr-5">
                <img src="https://static.thenounproject.com/png/1648939-200.png" 
                     alt="Question Mark Error Icon" 
                     class="w-28 h-auto error-icon-filter lg:w-32 md:w-25 sm:w-20">
            </div>
            <h1 class="text-8xl font-bold text-white m-0 text-shadow lg:text-9xl md:text-7xl sm:text-5xl">
                <?php echo $errorCode; ?>
            </h1>
        </div>
        
        <p class="text-2xl my-5 text-white ml-12 text-shadow-sm leading-relaxed lg:text-2xl md:text-xl sm:text-lg sm:ml-5">
            <?php echo $displayMessage; ?>
        </p>
        
        <?php if (is_numeric($errorCode)): ?>
        <div class="bg-gray-800 p-4 rounded-xl ml-12 mt-5 border border-gray-700 shadow-lg sm:ml-5">
            <p class="my-1 text-gray-300 text-base">
                <strong class="text-white">What happened:</strong>
            </p>
            <?php if ($errorCode == '400'): ?>
                <p class="my-1 text-gray-300 text-base">• Check your question title, text, or search parameters</p>
                <p class="my-1 text-gray-300 text-base">• Ensure all required fields are filled out correctly</p>
            <?php elseif ($errorCode == '401'): ?>
                <p class="my-1 text-gray-300 text-base">• Please log in to your qOverflow account</p>
                <p class="my-1 text-gray-300 text-base">• Your session may have expired</p>
            <?php elseif ($errorCode == '403'): ?>
                <p class="my-1 text-gray-300 text-base">• You may need higher privileges to perform this action</p>
                <p class="my-1 text-gray-300 text-base">• Check if you're trying to modify someone else's content</p>
            <?php elseif ($errorCode == '404'): ?>
                <p class="my-1 text-gray-300 text-base">• The question, answer, or user you're looking for doesn't exist</p>
                <p class="my-1 text-gray-300 text-base">• The content may have been deleted</p>
            <?php elseif ($errorCode == '409'): ?>
                <p class="my-1 text-gray-300 text-base">• A user with that username already exists</p>
                <p class="my-1 text-gray-300 text-base">• You may be trying to create duplicate content</p>
            <?php elseif ($errorCode == '422'): ?>
                <p class="my-1 text-gray-300 text-base">• Check your input format and requirements</p>
                <p class="my-1 text-gray-300 text-base">• Ensure passwords meet security requirements</p>
            <?php elseif ($errorCode >= 500): ?>
                <p class="my-1 text-gray-300 text-base">• This is a temporary server issue</p>
                <p class="my-1 text-gray-300 text-base">• Your data is safe, please try again in a few moments</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <p class="text-2xl my-5 text-white ml-12 text-shadow-sm leading-relaxed lg:text-2xl md:text-xl sm:text-lg sm:ml-5">
            Try refreshing the page or go back to the 
            <a href="/" class="text-blue-400 underline text-2xl mt-5 inline-block transition-colors duration-300 hover:text-blue-300 text-shadow-sm lg:text-2xl md:text-xl sm:text-lg">qOverflow home page</a>.
        </p>
    </div>

    <!-- Responsive stuff -->
    <style>
        @media (max-width: 500px) {
            .flex-row {
                flex-direction: column !important;
                text-align: center !important;
                margin-left: 0 !important;
            }
            .mr-5 {
                margin-right: 0 !important;
                margin-bottom: 0.625rem !important;
            }
            .ml-12, .sm\\:ml-5 {
                margin-left: 0 !important;
                text-align: center !important;
            }
        }
    </style>
</body>
</html>