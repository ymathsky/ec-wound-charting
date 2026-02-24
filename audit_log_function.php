<?php
// Filename: ec/audit_log_function.php

/**
 * Logs an action to the audit_log table.
 *
 * @param mysqli $conn The database connection object.
 * @param int|null $user_id The ID of the user performing the action. Can be null if not applicable.
 * @param string|null $user_name The name or identifier (like email) of the user.
 * @param string $action_type The type of action (e.g., 'LOGIN', 'VIEWED').
 * @param string|null $entity_type The type of entity being affected (e.g., 'user', 'patient'). Defaults to NULL.
 * @param int|null $entity_id The ID of the entity being affected. Defaults to NULL.
 * @param string $details A description of the event.
 */
function log_audit($conn, $user_id, $user_name, $action_type, $entity_type = NULL, $entity_id = NULL, $details = "") {
    try {
        // Check if connection is valid before proceeding
        if (!$conn || $conn->connect_error) {
            error_log("Audit Log Failed: Invalid database connection.");
            return;
        }

        // --- Get IP Address ---
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        // --- Prepare SQL Statement ---
        // Note: The signature now expects 7 arguments, and the function definition
        // uses default values for the optional ones to protect against old calls.
        $sql = "INSERT INTO audit_log (user_id, user_name, action, entity_type, entity_id, ip_address, details) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            // Log error if prepare fails
            error_log("Audit Log SQL Prepare Failed: " . $conn->error);
            return;
        }

        // --- Ensure $entity_id is an integer or NULL ---
        // bind_param requires exact types. We ensure the types passed match the template "isssiss".
        // If $entity_id is not set, convert it to 0 for the integer binding (if your DB allows 0 for NULL-like behavior on INT)
        $entity_id_int = is_numeric($entity_id) ? (int)$entity_id : 0;

        // --- Bind Parameters ---
        $stmt->bind_param("isssiss",
            $user_id,      // i - user_id
            $user_name,    // s - user_name
            $action_type,  // s - action
            $entity_type,  // s - entity_type
            $entity_id_int, // i - entity_id (must be an integer, 0 if NULL)
            $ip_address,   // s - ip_address
            $details       // s - details
        );

        // --- Execute Statement ---
        if (!$stmt->execute()) {
            // Log error if execute fails
            error_log("Audit Log SQL Execute Failed: " . $stmt->error);
        }

        // --- Close Statement ---
        $stmt->close();

    } catch (Exception $e) {
        // Log any exception that occurs during the logging process
        error_log("Audit Log Exception: " . $e->getMessage());
    }
}
?>