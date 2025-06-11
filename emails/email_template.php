<?php

// Ensure APP_NAME is available, with a fallback.
// This part assumes config.php might have been included by the calling script.
// If not, the calling script needs to ensure APP_NAME is passed or available.
if (!defined('APP_NAME')) {
    // Attempt to load config if this template is used standalone or very early
    if (file_exists(__DIR__ . '/../config.php')) {
        @include_once __DIR__ . '/../config.php';
    }
    if (!defined('APP_NAME')) { // If still not defined
        define('APP_NAME', 'Our Savings System'); // Basic fallback
    }
}

/**
 * Generates a basic HTML email structure.
 *
 * @param string $body_html_content The main HTML content for the email body.
 * @param string $app_name The name of the application, for header/footer. Defaults to APP_NAME constant.
 * @return string The full HTML email string.
 */
function generateBasicEmailTemplate(string $body_html_content, string $app_name = APP_NAME): string {
    // Inline CSS for better email client compatibility
    $styles = [
        'body' => 'margin: 0; padding: 0; width: 100% !important; background-color: #f4f4f4; font-family: Arial, sans-serif; color: #333333; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;',
        'wrapper' => 'width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 0; border-collapse: collapse;',
        'header' => 'background-color: #004085; color: #ffffff; padding: 20px; text-align: center;',
        'header_h1' => 'margin: 0; font-size: 24px; font-weight: bold; color: #ffffff;',
        'content_td' => 'padding: 30px 20px; line-height: 1.6; color: #333333; font-size: 16px;',
        'footer_td' => 'background-color: #eeeeee; color: #777777; padding: 20px; text-align: center; font-size: 12px;',
        'footer_a' => 'color: #004085; text-decoration: none;',
        'button_a' => 'display: inline-block; background-color: #007bff; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;',
        'paragraph' => 'margin: 0 0 1em 0;', // Consistent paragraph spacing
    ];

    // Ensure body_html_content paragraphs also get some basic styling if not already styled
    // This is a simple approach; more complex parsing might be needed for robust default styling.
    // For now, we assume $body_html_content is mostly <p> tags or styled blocks.
    // A common pattern for links in body content that should look like buttons:
    // <a href="..." class="button">Action Text</a> and then style .button in the <style> block.
    // However, for max compatibility, inline styles on the <a> tag itself are better for buttons.
    // The provided $body_html_content is expected to have its own styling for elements like buttons.

    $html = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email from {$app_name}</title>
    <style type="text/css">
        /* Basic responsive styling */
        body { {$styles['body']} }
        a { color: #007bff; text-decoration: underline; } /* Default link styling */
        p { {$styles['paragraph']} }
        @media screen and (max-width: 600px) {
            .wrapper { width: 100% !important; padding: 0 !important; }
            .content-td { padding: 20px 10px !important; }
            .header h1 { font-size: 20px !important; }
        }
    </style>
</head>
<body style="{$styles['body']}">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table class="wrapper" width="600" border="0" cellpadding="0" cellspacing="0" style="{$styles['wrapper']}">
                    <!-- Header -->
                    <tr>
                        <td class="header" style="{$styles['header']}">
                            <h1 style="{$styles['header_h1']}">{$app_name}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content-td" style="{$styles['content_td']}">
                            {$body_html_content}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer-td" style="{$styles['footer_td']}">
                            <p style="{$styles['paragraph']}">&copy; {date('Y')} {$app_name}. All rights reserved.</p>
                            <p style="{$styles['paragraph']}">If you have any questions, please contact our support team.</p>
                            <!-- Optional: <p><a href="[Your Website URL]" style="{$styles['footer_a']}">Visit our website</a></p> -->
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
EOT;

    return $html;
}

// Example Usage (for testing this file directly, not part of the actual template)
/*
if (basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"])) {
    if (!defined('APP_NAME')) define('APP_NAME', 'My Test App'); // Ensure APP_NAME is defined for direct test

    $test_body_content = "
        <p style=\"margin: 0 0 1em 0;\">Dear User,</p>
        <p style=\"margin: 0 0 1em 0;\">This is a <strong>test email body</strong> with some details. We hope you find this useful.</p>
        <p style=\"margin: 0 0 1em 0;\">Please click the button below to proceed:</p>
        <p style=\"margin: 0 0 1em 0; text-align: center;\">
            <a href=\"#\" style=\"display: inline-block; background-color: #007bff; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;\">Action Button</a>
        </p>
        <p style=\"margin: 0 0 1em 0;\">If you have any questions, feel free to reach out.</p>
        <p style=\"margin: 0 0 1em 0;\">Thank you!</p>
    ";
    echo generateBasicEmailTemplate($test_body_content);
}
*/
?>
