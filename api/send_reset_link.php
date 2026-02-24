<?php
// Filename: ec/api/send_reset_link.php

header('Content-Type: application/json');

// Include DB connection
require_once '../db_connect.php';

// Include Email Configuration
require_once '../config_email.php';

// --- UPDATED PHPMailer Inclusion ---
// Assuming PHPMailer's main files are located in 'ec/PHPMailer/src/'
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit;
}

$email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);

// 1. Check if user exists
try {
    $sql = "SELECT user_id, full_name FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // IMPORTANT SECURITY STEP: Show success message even if email is not found.
    if (!$user) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'If the email is registered, a password reset link has been sent.']);
        exit;
    }

    $user_id = $user['user_id'];

    // 2. Generate and Store Token (as before)
    $token = bin2hex(random_bytes(32)); // 64 character hex token
    $expires_at = date("Y-m-d H:i:s", time() + 3600); // Token expires in 1 hour

    // Delete any existing token for this user first
    $delete_sql = "DELETE FROM password_resets WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Insert new token
    $insert_sql = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iss", $user_id, $token, $expires_at);
    $insert_stmt->execute();
    $insert_stmt->close();


    // 3. SEND EMAIL using PHPMailer
    $reset_link = "https://www.ecwoundcharting.com/reset_password.php?token=" . $token; // *** UPDATED WITH YOUR DOMAIN ***

    // Passing 'true' enables exceptions
    $mail = new PHPMailer(true);
    try {
        // Server settings (using user's provided details from config_email.php)
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE; // 'ssl'
        $mail->Port       = SMTP_PORT;     // 465

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $user['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request for EC Wound Charting';
        $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2 style='color: #2563eb;'>Password Reset</h2>
                <p>Hello {$user['full_name']},</p>
                <p>You recently requested a password reset for your EC Wound Charting account. Click the link below to proceed:</p>
                <p style='margin: 20px 0;'>
                    <a href='{$reset_link}' style='display: inline-block; padding: 10px 20px; color: #fff; background-color: #2563eb; text-decoration: none; border-radius: 5px;'>
                        Reset My Password
                    </a>
                </p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, please ignore this email.</p>
                <p>Thank you,<br>EC Wound Charting Support Team</p>
            </body>
            </html>
        ";

        $mail->send();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email. Check your spam folder if you don\'t see it soon.']);

    } catch (Exception $e) {
        // Log detailed error but show a generic message to the user
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not send the reset email due to a server error. Please contact support.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in send_reset_link.php (DB connection/token storage): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

$conn->close();
?>