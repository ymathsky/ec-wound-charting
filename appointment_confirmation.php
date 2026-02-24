<?php
// Filename: ec/appointment_confirmation.php

session_start();
// Include the header (which likely includes db_connect.php, session checks, and UI boilerplate)
include 'templates/header.php';
?>

    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-12">
                        <h1 class="text-success"><i class="fas fa-calendar-check"></i> Appointment Scheduled!</h1>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-success card-outline">
                            <div class="card-body">
                                <div class="text-center">
                                    <h3 class="display-4 text-success mb-4">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </h3>
                                    <p class="lead">The patient appointment has been successfully scheduled.</p>
                                    <p class="text-muted">An email notification has been sent to the assigned clinician in the background.</p>
                                    <hr>
                                    <a href="appointments_calendar.php" class="btn btn-primary btn-lg mt-3">
                                        <i class="fas fa-calendar-alt"></i> View Calendar
                                    </a>
                                    <a href="dashboard.php" class="btn btn-secondary btn-lg mt-3">
                                        <i class="fas fa-home"></i> Go to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

<?php
// Include the footer
include 'templates/footer.php';
?>