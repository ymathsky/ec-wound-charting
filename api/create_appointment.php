<?php
// Filename: api/create_appointment.php

// Prevent HTML errors from breaking JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Use statements must be at top level
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Buffer output so we can calculate content length
ob_start();

try {
    require_once '../db_connect.php';

    // Check if PHPMailer exists before requiring
    $phpmailer_path = '../PHPMailer/src/PHPMailer.php';
    $has_mailer = file_exists($phpmailer_path);

    if ($has_mailer) {
        require_once '../PHPMailer/src/PHPMailer.php';
        require_once '../PHPMailer/src/Exception.php';
        require_once '../PHPMailer/src/SMTP.php';
    }

    $data = json_decode(file_get_contents("php://input"));

    // Basic validation
    if (empty($data->patient_id) || empty($data->user_id) || empty($data->appointment_date)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data. Patient, Clinician, and Appointment Date are required."]);
        ob_end_flush();
        exit();
    }

    // Prepare variables outside the try block for error reporting scope
    $patient_id = intval($data->patient_id);
    $user_id = intval($data->user_id); // The clinician
    $appointment_date = htmlspecialchars(strip_tags($data->appointment_date));
    $status = "Scheduled"; // Default status for new appointments
    $appointment_type = isset($data->appointment_type) ? htmlspecialchars(strip_tags($data->appointment_type)) : 'Follow Up Visit';
    $notes = isset($data->notes) ? htmlspecialchars(strip_tags($data->notes)) : null;

    // --- CONFLICT CHECK: Ensure slot is not already taken ---
    $check_sql = "SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND appointment_date = ? AND status != 'Cancelled'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $appointment_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $row = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($row['count'] > 0) {
        ob_clean();
        http_response_code(409); // Conflict
        echo json_encode(["message" => "This time slot has already been booked by another user. Please select a different time."]);
        ob_end_flush();
        exit();
    }
    // -------------------------------------------------------

    // 1. Insert Appointment (Main Operation)
    $sql = "INSERT INTO appointments (patient_id, user_id, appointment_date, status, appointment_type, notes) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $patient_id, $user_id, $appointment_date, $status, $appointment_type, $notes);

    if ($stmt->execute()) {
        $stmt->close();

        // --- NEW LOGIC: Automatically update Patient Status to 'on_going' ---
        $update_status_sql = "UPDATE patients SET status = 'on_going', last_updated_by = ? WHERE patient_id = ?";
        $stmt_status = $conn->prepare($update_status_sql);
        if ($stmt_status) {
            $current_user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : null;
            $stmt_status->bind_param("ii", $current_user_id, $patient_id);
            $stmt_status->execute();
            $stmt_status->close();
        }

        // 2. Fetch necessary details BEFORE closing the connection for background task
        $patient_name = 'N/A';
        $patient_sql = "SELECT first_name, last_name FROM patients WHERE patient_id = ?";
        $patient_stmt = $conn->prepare($patient_sql);
        if ($patient_stmt) {
            $patient_stmt->bind_param("i", $patient_id);
            $patient_stmt->execute();
            $patient_result = $patient_stmt->get_result();
            if ($patient = $patient_result->fetch_assoc()) {
                $patient_name = $patient['first_name'] . ' ' . $patient['last_name'];
            }
            $patient_stmt->close();
        }

        $clinician_name = 'Clinician';
        $clinician_email = null;
        $clinician_sql = "SELECT full_name, email FROM users WHERE user_id = ?";
        $clinician_stmt = $conn->prepare($clinician_sql);
        if ($clinician_stmt) {
            $clinician_stmt->bind_param("i", $user_id);
            $clinician_stmt->execute();
            $clinician_result = $clinician_stmt->get_result();
            if ($clinician = $clinician_result->fetch_assoc()) {
                $clinician_name = $clinician['full_name'];
                $clinician_email = $clinician['email'];
            }
            $clinician_stmt->close();
        }

        // 3. Send Success Response to Client (Non-Blocking)
        session_write_close();
        ignore_user_abort(true);

        http_response_code(201);
        echo json_encode([
            "message" => "Appointment created successfully. Processing email notification in background.",
            "redirect_url" => "appointment_confirmation.php"
        ]);

        // Send headers to close connection
        $size = ob_get_length();
        header("Content-Length: $size");
        header("Connection: close");
        ob_end_flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }

        // 4. --- START BACKGROUND EMAIL NOTIFICATION LOGIC ---
        if ($has_mailer && $clinician_email) {
            $config_path = '../config_email.php';
            if (file_exists($config_path)) {
                require_once $config_path;
                
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = SMTP_SECURE;
                        $mail->Port = SMTP_PORT;

                        $mail->setFrom(FROM_EMAIL, FROM_NAME);
                        $mail->addAddress($clinician_email, $clinician_name);

                        $mail->isHTML(true);
                        $mail->Subject = 'New Appointment Scheduled: ' . $patient_name;
                        
                        // Enhanced HTML Email Template
                        $formatted_date = date('F j, Y, g:i a', strtotime($appointment_date));
                        $notes_display = htmlspecialchars($notes ?? 'None provided');
                        
                        $email_body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                            <style>
                                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #374151; background-color: #f3f4f6; margin: 0; padding: 0; }
                                .email-container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
                                .header { background-color: #4f46e5; padding: 30px 20px; text-align: center; }
                                .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; }
                                .content { padding: 40px 30px; }
                                .greeting { font-size: 18px; margin-bottom: 20px; color: #111827; }
                                .details-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 25px 0; }
                                .detail-item { padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
                                .detail-item:last-child { border-bottom: none; }
                                .label { font-weight: 600; color: #6b7280; display: inline-block; width: 140px; }
                                .value { color: #111827; font-weight: 500; }
                                .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
                            </style>
                        </head>
                        <body>
                            <div class='email-container'>
                                <div class='header'>
                                    <h1>Appointment Scheduled</h1>
                                </div>
                                <div class='content'>
                                    <p class='greeting'>Hello $clinician_name,</p>
                                    <p>A new appointment has been successfully booked for your patient. Here are the details:</p>
                                    
                                    <div class='details-box'>
                                        <div class='detail-item'>
                                            <span class='label'>Patient Name:</span>
                                            <span class='value'>$patient_name</span>
                                        </div>
                                        <div class='detail-item'>
                                            <span class='label'>Date & Time:</span>
                                            <span class='value'>$formatted_date</span>
                                        </div>
                                        <div class='detail-item'>
                                            <span class='label'>Type:</span>
                                            <span class='value'>$appointment_type</span>
                                        </div>
                                        <div class='detail-item'>
                                            <span class='label'>Notes:</span>
                                            <span class='value'>$notes_display</span>
                                        </div>
                                    </div>

                                    <p>Please log in to the EC Wound Charting portal to view the full patient record.</p>
                                </div>
                                <div class='footer'>
                                    <p>&copy; " . date('Y') . " EC Wound Charting. All rights reserved.</p>
                                    <p>This is an automated notification. Please do not reply to this email.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $mail->Body = $email_body;
                        $mail->AltBody = "New Patient Appointment Scheduled. Patient: $patient_name. Date & Time: $appointment_date. Type: $appointment_type. Notes: " . ($notes ?? 'N/A') . ".";

                        $mail->send();
                        error_log("Clinician appointment notification successfully sent to: " . $clinician_email);
                    } catch (Exception $e) {
                        error_log("BACKGROUND TASK: Appointment email failed to send to $clinician_email. Mailer Error: {$mail->ErrorInfo}");
                    }
                } else {
                    error_log("BACKGROUND TASK: PHPMailer class not found despite file check.");
                }
            } else {
                error_log("BACKGROUND TASK: config_email.php not found. Email skipped.");
            }
        } else {
            error_log("BACKGROUND TASK: Appointment created but no clinician email found or PHPMailer missing. No email sent.");
        }
        // --- END BACKGROUND EMAIL NOTIFICATION LOGIC ---

        exit();

    } else {
        $error_detail = $stmt->error;
        $stmt->close();
        throw new Exception("Database execution failed: " . $error_detail);
    }

} catch (Exception $e) {
    ob_clean(); // Clear any partial output
    http_response_code(500);
    echo json_encode(["message" => "Unable to create appointment.", "error" => $e->getMessage()]);
    ob_end_flush();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
?>