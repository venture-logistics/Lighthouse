<?php

// Clean user input
function clean($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format currency
function format_money($amount)
{
    return number_format($amount, 2);
}

// Check if user is logged in
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

// Flash messages
function set_message($message, $type = 'success')
{
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function display_message()
{
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'];
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    return '';
}
function get_status_color($status)
{
    return match (strtolower($status)) {
        'paid' => 'success',
        'pending' => 'warning',
        'overdue' => 'danger',
        'draft' => 'secondary',
        'cancelled' => 'dark',
        default => 'primary'
    };
}

// In config.php
define('CURRENCY_SETTINGS', [
    'currency' => '$',
    'decimals' => 2,
    'decimal_separator' => '.',
    'thousands_separator' => ',',
    'currency_position' => 'before',
    'space_between' => false
]);

// Then update the format_currency function
function format_currency($amount, array $options = [])
{
    // Merge with global settings first, then with provided options
    $opts = array_merge(CURRENCY_SETTINGS, $options);
    // ... rest of the function
}

/**
 * Format date with enhanced options
 * @param string|null $date The date to format
 * @param string|array $options Format string or array of options
 * @return string Formatted date
 */
function format_date($date, $options = 'Y-m-d')
{
    // Return empty string if date is null or empty
    if (empty($date)) {
        return '';
    }

    // Default options
    $defaults = [
        'format' => 'Y-m-d',
        'timezone' => date_default_timezone_get(),
        'locale' => 'en_US'
    ];

    // If options is string, treat it as format
    if (is_string($options)) {
        $options = ['format' => $options];
    }

    // Merge with defaults
    $options = array_merge($defaults, (array) $options);

    try {
        // Create DateTime object
        $dateObj = new DateTime($date);

        // Set timezone if specified
        if ($options['timezone']) {
            $dateObj->setTimezone(new DateTimeZone($options['timezone']));
        }

        // If locale is specified and IntlDateFormatter is available, use it
        if ($options['locale'] && class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter(
                $options['locale'],
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE
            );
            return $fmt->format($dateObj);
        }

        // Fall back to basic date formatting
        return $dateObj->format($options['format']);

    } catch (Exception $e) {
        error_log("Date formatting error: " . $e->getMessage());
        return '';
    }
}