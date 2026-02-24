EC Wound Charting System - Release Log

[v1.3.15] - 2026-01-10

Pre-Debridement Workflow & AI Data Integrity

**Smart Pre-Debridement Detection:**
**Context Awareness:** The AI now intelligently detects "Pre-Debridement" context from voice commands (e.g., "Update Pre-Debridement assessment", "Start of visit") and explicitly routes data to the pre-procedure record instead of overwriting post-procedure values.
**Image Annotation Sync:** Images labeled as "Pre-Debridement" in the Annotation Modal are now strictly enforced as such during AI analysis, preventing miscategorization.

**Data Accuracy & Integrity:**
**Procedure Note Integration:** Pre-debridement measurements captured by the AI are now automatically injected into the generated Procedure Note narrative, replacing "To be determined" placeholders with actual values (Length x Width x Depth).
**Assessment Data Fixes:** Fixed a critical bug where specific text fields (Drainage Type, Exudate Amount, Odor) were failing to save during voice updates due to internal type mismatches.
**Review Modal Fix:** resolved an issue where the "Review AI Extraction" modal would occasionally appear empty; it now reliably populates with the structured data returned by the AI.

[v1.3.14] - 2025-12-16

AI Analysis & Dictation Workflow Improvements

**AI Image Analysis & Review:**
**Structured Dropdowns:** The AI Review Modal now uses standardized dropdown menus for **Wound Location** and **Wound Type** instead of free-text inputs. This ensures cleaner data entry and consistency with the database.
**Enhanced Image Analysis:** Updated the AI backend to prioritize image analysis when a photo is present, automatically extracting dimensions and tissue types.
**Auto-Trigger:** Uploading a photo in the AI Assistant now automatically triggers the "Analyze this image" command, streamlining the workflow.

**Dictation Mode (Narrative):**
**Draft Persistence:** Fixed an issue where dictation drafts were deleted after saving. Now, the draft text remains in the editor even after processing and saving, allowing clinicians to easily "add on" to their dictation.
**Workflow Continuity:** Saving a dictation now redirects to the Visit Summary for review but preserves the local draft state, enabling seamless switching back to Dictation Mode if further edits are needed.

[v1.3.13] - 2025-12-09

Simplified Documentation & Voice Enhancements

**Simplified Note (Visit Summary) Parity:**
**Template Management:** Brought full "Save as Template" functionality to the Simplified Mode. Clinicians can now save, load, and manage text templates directly within the simplified view, matching the Advanced Mode's capabilities.
**Sign & Finalize Workflow:** Implemented a complete "Sign & Finalize" workflow in Simplified Mode. Clinicians can now sign the note using a signature pad and finalize the visit without leaving the summary page.
**Read-Only State:** Finalized notes now automatically lock all fields and replace the signature pad with the saved signature image to ensure data integrity.
**Addendum Rendering:** Fixed HTML rendering issues in the Addendum section to ensure formatted text displays correctly.

**Navigation & UI Clarity:**
**Renamed Workflows:** Renamed "Visit Note" to **"Advanced Note"** and "Summary" to **"Simplified Note"** in the sidebar and top navigation to clearly distinguish between the two documentation modes.

**Wound Assessment Voice Features:**
**Draggable Voice Menus:** Both the "Wound Assistant" (Guided Mode) and "Smart Voice Entry" (Command Mode) menus are now floating and draggable, allowing clinicians to position them anywhere on the screen.
**Smart Voice FAB:** Added a dedicated Floating Action Button (Purple Mic) for quick access to Smart Voice Entry.
**Continuous Listening:** Updated Smart Voice Entry to support **continuous listening**, allowing clinicians to dictate multiple fields in sequence without restarting the microphone.
**Voice Commands:** Added "Stop" and "Cancel" commands to easily end the voice session hands-free.

[v1.3.12] - 2025-12-06

Voice-Activated Clinical Assistant

**Voice Vitals Entry:**
**Hands-Free Documentation:** Introduced a "Voice Mode" for the Vitals workflow. Clinicians can now click the microphone icon to have the system verbally guide them through recording Height, Weight, Blood Pressure, Heart Rate, and more.
**Smart Speech Parsing:** The assistant intelligently converts spoken medical data into structured formats (e.g., "One twenty over eighty" becomes `120/80`, "Five foot five" becomes `65` inches).
**Conversational Control:** Supports natural language commands for navigation and correction. Clinicians can say "Update height to 70", "Go back to weight", or "Skip" to manage the workflow flexibly.
**Workflow Navigation:** Added voice commands like "Go to HPI" or "Finish vitals" to automatically save data and transition to the next stage of the visit.
**Seamless Integration:** All voice-entered data triggers the existing Autosave system immediately, ensuring no data is lost.

[v1.3.11] - 2025-12-02

Clinical Documentation & Workflow Enhancements

**Smart Copy from History:**
**Granular Copying:** Clinicians can now copy specific sections (e.g., just the HPI or Plan) from a past visit note directly into the current note without overwriting other sections.
**Full Note Clone:** The "Copy All" function remains available to clone an entire previous visit note for rapid documentation of follow-up visits.
**Visual Feedback:** Added clear "Copy" buttons to each section in the history sidebar that appear on hover, keeping the interface clean.

**Checklist & Formatting Refinements:**
**Numbered Lists:** Updated the Quick Insert Checklist to use numbered lists (`<ol>`) instead of bullet points for better readability and professional formatting.
**Smart Order Formatting:** Logic updated to automatically flatten "Order" checklists (Labs, Imaging) into a single, clean numbered list without redundant category headers.

**Report Polish:**
**Clean Output:** Removed hardcoded placeholder text (e.g., "Nutrition Education") from the final Visit Report to ensure only documented clinical data appears on the printed record.


[v1.3.10] - 2025-11-27

Wound Assessment Workflow & UI

**Smart Form Reset:** Uploading a "Pre-debridement" photo now automatically triggers a new assessment draft, clearing previous fields to streamline the workflow for new entries.
**Default Image Type:** The image type dropdown now defaults to "Pre-debridement" to reduce clicks for the most common workflow.
**Enhanced Details Modal:** The "Assessment Details" modal (accessed via the history list) now displays the associated wound photo alongside the clinical data for better context.
**Upload Validation:** Added client-side validation to prevent uploads without a selected Image Type, resolving API errors.


[v1.3.9] - 2025-11-27

Wound Assessment & Gallery Overhaul

Assessment History Tab:
**Grouped View:** Assessments are now intelligently grouped by Appointment ID, providing a cleaner chronological view of patient progress.
**Visual Tissue Analysis:** Added a dynamic "Tissue Composition" bar that visually represents the percentage of Granulation (Red), Slough (Yellow), and Eschar (Black) for immediate clinical insight.
**Enhanced Metrics:** The history table now displays comprehensive dimensional data (Length x Width x Depth + Total Area) and detailed Drainage characteristics (Type & Amount).

Photo Gallery Tab:
**Card Layout:** Completely redesigned the gallery into a modern, responsive card grid. Each card features the wound image, date, and a distinct "Image Type" badge (e.g., Pre-Debridement).
**Load & Edit Workflow:** Replaced the static "Details" view with a powerful **"Load"** function. Clinicians can now click the pencil icon on any historical photo to instantly load that specific assessment into the main form for review or editing.
**Smart Delete Protection:** The "Delete" button is now context-aware. It automatically disables itself if the visit has been finalized/signed, preventing accidental data loss in permanent records.

System Stability:
**File Recovery:** Repaired corrupted logic files to restore full system functionality.
**Error Handling:** Resolved API 400 errors during load/save operations and fixed "Finalize" button behavior to prevent premature form submissions.


[v1.3.8] - 2025-11-26

Deployment & Infrastructure

Production Readiness:

Created a robust database migration script (update_db.sql) using IF NOT EXISTS logic to safely update production schemas without data loss.

Configured production environment variables (.env.production) for secure deployment.

Established SFTP configuration for cPanel hosting deployment.

Workflow Automation

Patient Status Automation:

Updated api/create_appointment.php to automatically transition a patient's status to 'on_going' immediately after their first appointment is scheduled, reducing manual administrative work.

Role-Based Access Control (RBAC)

Dashboard Access:
Updated `templates/sidebar.php` to explicitly grant the 'clinician' role access to the Dashboard, ensuring care providers have immediate visibility into their daily schedule and tasks.

Clinical Documentation Enhancements

Visit Diagnosis (`visit_diagnosis.php`):
**AI-Powered Suggestions:** Integrated an intelligent suggestion engine that analyzes HPI and Wound data to recommend relevant ICD-10 codes.
**Patient History Import:** Added a "Patient History" sidebar allowing clinicians to bulk-import diagnoses from previous encounters with a single click.

Visit Report (`visit_report.php`):
**Granular Wound Reporting:** Enhanced the report to display detailed wound metrics including Tunneling, Undermining, Tissue Composition (Granulation/Slough/Eschar %), and Exposed Structures.
**Critical Finding Alerts:** Added visual highlighting for critical findings (e.g., "Exposed Bone") directly on the printed report.
**Automated Procedure Narratives:** Implemented logic to auto-generate professional narrative text for Debridements and Skin Graft applications based on structured assessment data.

Visit Notes (`visit_notes.php`):
**Addendum Workflow:** Implemented a secure "Addendum" system allowing clinicians to append additional notes to finalized/signed visits without invalidating the original signature.


[v1.3.7] - 2025-11-25

Workflow Automation & Efficiency

Clone Last Visit (Enhanced):

Replaced the native browser confirmation dialog with a polished, Tailwind CSS-styled modal for a seamless user experience.

Centered the modal perfectly on all screen sizes using Flexbox.

Hardened the backend logic (api/get_last_visit_note.php) to use MySQLi for better stability and error handling.

Improved the frontend logic to safely overwrite existing content without duplication or formatting errors.

Data Integrity & Security

Database Resilience:

Fixed a critical Foreign Key constraint error in api/save_visit_note.php that caused saves to fail for certain user sessions.

Implemented robust fallback logic for User IDs (defaulting to NULL if invalid) to ensure notes are always saved.

Added database transaction support (Begin/Commit/Rollback) to guarantee data consistency during save operations.

Content Sanitization:

Implemented aggressive HTML cleaning in the save process to strip empty paragraphs and excessive whitespace generated by the rich text editor, ensuring clean and professional final reports.



[v1.3.6] - 2025-11-24

Patient Portal UI/UX & Feature Consolidation

Comprehensive Navigation Overhaul:

Fixed Sidebar Layout: Implemented a unified, fixed sidebar/mobile fly-out navigation (nav_panel.php) across all patient portal pages for consistent access.

Mobile Experience: Improved the mobile menu toggle visibility and ensured the sidebar slides over the content on small screens, preventing layout overlaps.

Dedicated Appointments Module:

New Page: Created dedicated Appointments page (appointments.php) listing all patient visits (Upcoming, Pending, History).

Table View: Switched from a basic card view to a highly readable, responsive table structure for appointment history, enhancing scannability.

Data Integrity: Implemented robust status handling in JavaScript to correctly render patient-requested appointments (status "" is displayed as Pending).

UX: Enhanced the visual hierarchy of each table row with clear date, type, and color-coded status badges.

Photo Upload Page Enhancement (upload_photo.php):

Auto-Upload Feature: Removed the manual "Upload Photo" button; the upload process now starts automatically upon selecting a file, significantly improving workflow efficiency.

Advanced UX: Refined the file selection drop zone with clearer iconography and feedback.

Metric Card: Added a dynamic "Patient Uploads" metric card (mock data) to display the count of previously submitted photos for the selected wound, encouraging visual progress tracking.

Secure Messages Overhaul (messages.php):

Modern Inbox: Redesigned the message list into a cleaner inbox style.

Directional Cues: Applied distinct color-coded borders and iconography to visually separate messages sent by the Care Team (Green) from messages sent by the Patient (Indigo).

Dashboard (index.php) Restructure:

Summary Grid: Replaced the two-column layout with a new three-column Summary Grid focusing on Next Visit, Latest Instructions, and the Upload Photo action.

Removed Modal: The Appointment Request Modal and logic were removed from the Dashboard and moved entirely to the dedicated Appointments page.

General UI/UX:

Standardized Feedback: Replaced all native alert() calls with custom, in-page success/error message boxes across all portal forms (Upload Photo, Appointment Request, Profile Update) for a non-disruptive experience.

Enhanced Forms: Applied advanced button styling (shadows, hover effects) to all primary buttons (btn-primary) for improved visual engagement.



[v1.3.5] - 2025-11-24

Workflow Automation & Efficiency

Clone Last Visit: Added a "Clone Last Visit" button in the sticky header. This feature fetches the most recent finalized note for the patient and intelligently appends it to the current editor with a distinct separator, preserving HTML formatting.

"Insert Normal" (WNL) Buttons: Implemented one-click "Insert Normal" buttons for Subjective and Objective tabs. These populate detailed, multi-system normal findings (Cardio, Neuro, Resp, etc.) to accelerate charting by exception.

Text Macros (Dot Phrases): Integrated a real-time macro expander. Typing shortcuts like .normal, .pain, or .plan followed by a space now automatically expands into full clinical sentences.

Technical Refinements & Resilience

Offline Resilience: Implemented a "Local Mirror" backup system using localStorage. If the browser crashes or the internet connection is lost, the system detects the local backup on reload and offers to restore it, preventing data loss.

Optimistic Autosave UI: Enhanced the header status to provide immediate visual feedback ("Saving...") while the network request processes in the background. Added fallback states for offline scenarios.

Robust API Handling: Hardened the get_last_visit_note.php API to handle session authentication correctly and prevent JSON parsing errors caused by PHP warnings.


[v1.3.4] - 2025-11-24

Security

API Role-Based Access Control (RBAC): Hardened security across critical patient profile endpoints (delete_wound.php, update_patient_details.php, create_wound.php, manage_insurance.php). Implemented strict server-side session checks to block 'facility' users from modifying clinical data.

Secure Wound Creation: Introduced create_wound_from_profile.php, a dedicated endpoint for the profile dashboard to handle wound registration securely without affecting the active visit workflow.

Changed

Real-Time Insurance Data: Replaced frontend mock data with a fully functional Insurance module connected to the patient_insurance table.

Wound Dashboard UI: Streamlined the interface by removing the "View/Assess" link for non-admin users and replacing it with a "View Latest" modal trigger.

Chart Rendering: Validated and optimized the wound progress chart to correctly visualize Length, Width, and Depth trends over time.

Clinical Insights: Activated the "Regenerate" function, allowing clinicians to force a refresh of the AI analysis. Added audit logging for AI generation events.

Added

Latest Wound Detail Modal: Added a "View Latest" feature allowing clinicians to instantly inspect the most recent wound image, measurements, and assessment notes from the dashboard.

Removed

Interactive Body Map: Removed the SVG body map feature to simplify the UI and improve compatibility with touch devices.

Diagnosis Input: Removed the diagnosis input field from the "Register New Wound" modal to streamline the initial data entry process.



[v1.3.3] - 2025-11-24

UI/UX Overhaul - Visit Notes

Tabbed Interface: Converted the Visit Notes page from a vertical accordion layout to a modern Tabbed Interface. This reduces scrolling and focuses the clinician on one SOAP section at a time.

Themed Quick Insert: Enhanced the Quick Insert Modal to be context-aware and color-coded (Blue/Subjective, Green/Objective, etc.) matching the active section. Improved the layout with a sidebar for categories and card-style selection items.

Enhanced Readability: Increased Quill Editor font size to 16px and minimum height to 350px for a better writing experience.

Visual Data Summary: Redesigned auto-populated sections (Vitals, Diagnoses, Meds) into responsive grids and styled cards with icons. These are now placed below the editor to provide context without cluttering the workspace.

Sticky Header Status: Moved the Autosave status indicator to the sticky header, providing constant visibility of the "Saving..." and "Saved" states.

Added

Full Note Preview: Implemented a "Preview Full Note" feature that opens visit_report.php in a large modal Iframe, allowing clinicians to verify the exact final report format before signing.

Fixed

Autosave Conflict: Resolved "AutosaveManager start aborted" errors by removing conflicting script initializations and centralizing logic in visit_notes_logic.js.

Modal Triggers: Fixed issues where Quick Insert buttons would not open the modal due to missing event listeners or initialization timing issues.





[v1.3.2] - 2025-11-24

Security

API Role-Based Access Control (RBAC): Hardened security across critical patient profile endpoints (delete_wound.php, update_patient_details.php, create_wound.php, manage_insurance.php). Implemented strict server-side session checks to block 'facility' users from modifying clinical data.

Secure Wound Creation: Introduced create_wound_from_profile.php, a dedicated endpoint for the profile dashboard to handle wound registration securely without affecting the active visit workflow.

Changed

Real-Time Insurance Data: Replaced frontend mock data with a fully functional Insurance module connected to the patient_insurance table.

Wound Dashboard UI: Streamlined the interface by removing the "View/Assess" link for non-admin users and replacing it with a "View Latest" modal trigger.

Chart Rendering: Validated and optimized the wound progress chart to correctly visualize Length, Width, and Depth trends over time.

Added

Latest Wound Detail Modal: Added a "View Latest" feature allowing clinicians to instantly inspect the most recent wound image, measurements, and assessment notes from the dashboard.

Removed

Interactive Body Map: Removed the SVG body map feature to simplify the UI and improve compatibility with touch devices.

Diagnosis Input: Removed the diagnosis input field from the "Register New Wound" modal to streamline the initial data entry process.



[v1.3.1] - 2025-11-23

Added

Patient Labs & Orders Module (New): Implemented a comprehensive module for managing patient diagnostics.

Order Creation: Clinicians can now create new orders (Lab, Imaging, Consult, Treatment, Supplies) directly within the system.

Specific Wound Linking: Orders can be linked to a specific wound (e.g., "Wound Culture for Left Heel"), providing critical clinical context.

Smart Order Encoding: Implemented real-time auto-detection for order types. Typing keywords like "WBC" or "X-Ray" automatically categorizes the order as "Lab" or "Imaging," streamlining data entry.

Priority Levels: Added ability to set priority levels (Routine, Urgent, Stat) for better task management.

Supply Ordering: Added a dedicated "DME / Supplies" category to support facility supply requests.

Result Management Workflow:

Upload Results: Added functionality to upload result documents (PDF/Images) directly to an order.

Status Tracking: Orders progress through statuses: Ordered -> Pending -> Results Received -> Reviewed.

Embedded Viewer: Implemented a modal viewer for result documents (via iframe), allowing clinicians to view lab reports without leaving the application.

Changed

UI Polish: Standardized the header in the Labs & Orders page to dynamically display the patient's name (e.g., "John Doe's Labs & Orders"), consistent with the Patient Chart History view.

Patient Profile Navigation: Updated the patient profile sidebar to include a direct link to the new "Labs & Orders" module.

Database Schema: Enhanced the patient_orders table to support wound-specific linking (wound_id) and order prioritization.



[v1.3.0] - 2025-11-23

Added

Patient Labs & Orders Module: Implemented a comprehensive new module for managing patient diagnostics, accessible via the patient profile.

Order Creation: Clinicians can now create new orders (Lab, Imaging, Consult, Treatment, Supplies) directly within the system.

Specific Wound Linking: Orders can be linked to a specific wound (e.g., "Wound Culture for Left Heel"), enhancing clinical context.

Smart Order Encoding: Implemented real-time auto-detection for order types. Typing "WBC" or "X-Ray" automatically categorizes the order as "Lab" or "Imaging," streamlining data entry.

Priority Levels: Added ability to set priority levels (Routine, Urgent, Stat) for better task management.

Result Management Workflow:

Upload Results: Added functionality to upload result documents (PDF/Images) directly to an order.

Status Tracking: Orders progress through statuses: Ordered -> Pending -> Results Received -> Reviewed.

Embedded Viewer: Implemented a modal viewer for result documents, allowing clinicians to view lab reports without leaving the application.

DME & Supply Ordering: Added a dedicated "DME / Supplies" category to the order workflow to support facility supply requests.



Changed

Patient Profile Navigation: Updated the patient profile sidebar to include a direct link to the new "Labs & Orders" module.

Database Schema: Enhanced the patient_orders table to support wound-specific linking (wound_id) and order prioritization.


[v1.2.8] - 2025-11-21

Added

Wound Comparison Engine (Reconstructed): Completely rebuilt the logic and UI for wound_comparison.php to support a robust, multi-step clinical workflow.

Multi-Step Workflow: Implemented a 3-stage selection process:

Select Wound: Users first choose from a dropdown of the patient's active wounds.

Select Visits: Users click two distinct visits on the timeline to set the "Before" and "After" comparison points.

Compare: The interface dynamically loads the relevant images and data for side-by-side analysis.

Smart Image Slider: Re-engineered the image comparison slider to handle drag/touch events flawlessly and robustly select the best available image (prioritizing Post-Debridement -> Pre-Debridement) for each chosen visit.

Quantitative Data Table: Added a dynamic data table below the slider that compares key metrics (Length, Width, Depth, Area, Tissue %) between the two visits.

Clinical Context Highlighting: The data table now features intelligent highlighting:

Positive Trends (Green): Decreased Wound Area, Increased Granulation, Decreased Slough.

Negative Trends (Red): Increased Wound Area, Decreased Granulation, Signs of Infection (Odor, Erythema).

Robust Data API: Created a new backend endpoint api/get_wound_comparison_data.php that correctly aggregates wound details, assessments, and nested images into a single JSON structure, resolving previous data loading errors.

[v1.2.7] - 2025-11-21

Changed

Billing History UI/UX Overhaul (Master-Detail View): Redesigned patient_billing.php to match the modern charting standard.

Master-Detail Pattern: Implemented the Master-Detail layout for reviewing billing history (Visit List on the left, Superbill details on the right).

Expanded Layout: Removed horizontal width constraints for maximum screen utilization.

Persistent Demographics: Integrated patient demographics into a full-width header band above the Master/Detail columns for continuous context.

Optimized Scrolling: Fixed heights are applied to the Visit List and Superbill Detail sections, allowing independent scrolling for efficiency.

Superbill Viewing Workflow: Replaced the direct "Edit Superbill" link with a dedicated viewing/printing flow.

New Print Target: Created a separate, clean, print-optimized page (print_superbill.php) designed explicitly for generating PDF or paper copies of the Superbill.

Modal Viewer: The "View/Print Superbill" button now loads this print-optimized page inside a full-screen modal, allowing admins to preview and print without leaving the Billing History context.

Master List Enhancements: Applied the new explicit card design to the Visit History list items, including clear date blocks, clinician names, and "Finalized/Draft" status indicators.

[v1.2.6] - 2025-11-21

Changed

Past Charting UI/UX Overhaul (Master-Detail View): Completely redesigned patient_chart_history.php to enhance clinical scanning and navigation speed.

Master-Detail Pattern: Switched from a long scrolling list (accordion/timeline) to a three-column Master-Detail layout.

Master List: Displays a vertically scrollable list of visits (Date, Clinician, Status).

Detail Panel: Displays the full, detailed SOAP notes, Wound Assessments, and Images of the selected visit.

Expanded Layout: Removed horizontal width limits from the main container to utilize the full screen width for the Detail Panel.

Persistent Demographics: Moved key patient demographics (Name, DOB, Allergies, Clinician) to a full-width header band above the Master/Detail columns for constant visibility during scrolling.

Enhanced Visit Card Design: Redesigned list items with clear date blocks and explicit status indicators for rapid chronological scanning.

Optimized Scrolling: Implemented fixed heights (h-full-constrained) on the Master List and Detail Panel to allow independent vertical scrolling, ensuring optimal performance regardless of chart size.

[v1.2.5] - 2025-11-21

Changed

Patient EMR UI/UX Overhaul: Refactored patient_emr.php to align with the "Blank Page" standard for a modern, expansive design.

Full-Width Layout: Removed maximum width constraints (max-w-7xl) to allow the main content grid (Demographics and Documents) to stretch across the available screen width.

Header Redesign: Applied the standard text-3xl font-extrabold sticky header style for visual consistency.

Document Management UI: Implemented a Document Type Filter for the Saved Documents list, allowing clinicians to quickly categorize and view files.

Document Viewer Modal: Converted the document viewing process from opening in a new tab to using a dedicated, full-screen viewer modal.

Document Viewing Logic: Implemented conditional modal rendering: PDFs and images display directly via <iframe>, while unsupported files (like .doc, .docx) trigger an explicit error message and a dedicated "Direct Download" button for safer viewing.

Fixed

File Upload Critical Crash: Resolved the root cause PHP Parse error: Unmatched '}' and subsequent JavaScript SyntaxError: Unexpected token '<' in api/upload_document.php, ensuring document uploads now complete successfully.


[v1.2.4] - 2025-11-21

Added

Appointment Rescheduling: Implemented a robust rescheduling feature within the Patient Appointments view.

New Modal: Added a "Reschedule Appointment" modal accessible directly from the appointments list for 'Scheduled' and 'No-show' visits.

Clinician Assignment: The modal includes a dynamic dropdown to assign or re-assign a clinician during the rescheduling process.

Smart Logic: The backend (api/reschedule_appointment.php) automatically cancels the old appointment (preserving history) and creates a new linked appointment with the updated details.

Workflow Redirect: Upon successful rescheduling, the system now seamlessly redirects the user to add_appointment.php with the new appointment ID pre-loaded for immediate editing or confirmation.

Changed

Patient Appointments UI/UX Overhaul: Completely redesigned patient_appointments.php for a modern, card-based experience.

Tabbed Navigation: Replaced the long list with intuitive "Upcoming", "Past", and "All History" tabs to reduce clutter.

Card Layout: Shifted from a dense table to a clean, responsive card layout for each appointment, improving readability on all devices.

Visual Hierarchy: Enhanced the display of dates, statuses, and clinician names with distinct badges and typography.

Action Buttons: "Start Visit" and "Reschedule" actions are now prominent and context-aware.

Robust Data Loading: Improved the JavaScript logic for fetching clinician lists to handle various API response formats gracefully, preventing "Loading..." hangs.

New Appointment Workflow: Updated the "New Appointment" button in the header to point directly to add_appointment.php for a more streamlined creation process.

[v1.2.3] - 2025-11-21

Added

Clinical Visit Summary Report: Completely overhauled the visit_summary.php page to serve as a professional, printable "Clinical Visit Summary" document. It now features:

Dynamic Header: Auto-populates Facility Name and Clinician details based on patient assignment and appointment data.

Comprehensive Data Grid: Displays Vitals, HPI Narrative & Q&A, Encounter Diagnoses (ICD-10), Procedures (CPT), Active Medications, and Wound Assessments in a clean, card-based layout.

Skin Graft Checklist Integration: Automatically detects and displays graft product details (Serial, Lot, Sq Cm used) and compliance checks directly within the wound assessment card if a graft was applied during the visit.

Digital Signature Display: Renders the clinician's captured signature at the bottom of the report for authentication.

Fixed

Visit Summary Crashes: Resolved multiple critical fatal errors in visit_summary.php caused by schema mismatches:

Fixed Unknown column errors for assigned_clinician and facility_id by correctly joining the users table.

Corrected column mappings for patient_vitals (using blood_pressure, heart_rate, etc.) and wound_images (using image_path).

Added robust try-catch blocks around all data fetching sections (HPI, Diagnoses, Meds) to prevent the entire page from crashing if a single table is missing or empty.

[v1.2.2] - 2025-11-21

Added

Clinical Suggestions Module: Implemented a dedicated "Clinical Suggestions" system in the Visit Notes (Assessment & Plan sections). Clinicians can now browse a library of standardized orders, medications, and referrals via a modern modal and insert them directly into the SOAP note with a single click.

Enhanced Modal UI: Designed a polished, card-based interface for the Suggestions modal, featuring category icons (via Lucide), smooth animations, and clear "Insert" actions for improved usability.

Backend Integration: Added api/get_suggestions_for_notes.php to securely fetch active suggestions based on user session, ensuring data is always fresh from the database.

[v1.2.1] - 2025-11-21

Added

Digital Signature UI: Integrated a signature pad directly into visit_notes.php, allowing clinicians to draw their signature on-screen. The signature data is captured and saved with the visit note.

Wound Plans in Report: Updated visit_report.php to explicitly display the "Treatment / Plan" field for each wound assessment, ensuring that specific wound care instructions are visible in the final printed report.

Changed

Visit Notes Architecture: Significantly refactored visit_notes.php by moving inline JavaScript to dedicated external files (js/visit_notes_logic.js and js/visit_signature.js). This improves code readability, maintainability, and caching.

CDN Configuration: Removed strict Subresource Integrity (SRI) hashes from jQuery and Bootstrap script references to prevent browser blocking issues caused by CDN version mismatches.

Fixed

Note Saving Error: Resolved a critical database error ("Foreign key constraint fails") in api/save_visit_note.php by ensuring the patient_id is correctly captured and passed during the INSERT operation.

[v1.2.0] - 2025-11-21

Added

Copy Previous Assessment: A new button in wound_assessment.php allows clinicians to instantly copy measurements, characteristics, and details from the most recent assessment into the current form. This significantly speeds up documentation for chronic wounds.

Automated Letter of Medical Necessity (LMN): Added a "Generate LMN" button to the patient profile. This feature uses a smart template to auto-fill patient data, wound history, and treatment plans into a professional PDF document for insurance authorization.

Fixed

Date Formatting: Fixed an issue where appointment dates were displaying in the wrong timezone on the dashboard.

[v1.1.6] - 2025-11-20

Added

Graft Status Tracking: Added a "Graft Status" dropdown to the wound assessment form (Intact, Partial Loss, Failed) to track the progress of applied skin grafts over time.

Assessment ID Handling: Improved the robustness of saving wound assessments by ensuring the assessment_id is correctly passed back to the frontend after creation, preventing "Undefined ID" errors during immediate editing.

Apply Graft Link: Restored the functionality of the "Apply Graft" button by converting it to a standard link pointing to shoreline_skin_graft_checklist.php and adding logic to enable it only when a valid assessment draft exists.

[v1.1.5] - 2025-11-20

Added

Graft Audit Checklist: New standalone page shoreline_skin_graft_checklist.php for detailed graft compliance auditing.

Smart Form Logic: The wound assessment form now intelligently disables the "Apply Graft" link until an assessment is active to ensure data integrity.

Changed

Wound Assessment Logic: Refactored wound_assessment_logic.js to separate "Media" upload from "Data" entry, ensuring a new assessment ID is generated for every new photo upload to prevent overwriting history.

[v1.1.0] - 2025-10-25

Added

Auto-Narrative Generation: Implemented auto_narrative.js to synthesize discrete data points (vitals, HPI, wound measurements) into natural language text for the SOAP note.

Visit Workflow Navigation: Added a responsive, scrolling sub-menu to all visit pages (visit_vitals.php, visit_hpi.php, etc.) for better mobile navigation.

Fixed

Mobile Layout Issues: Adjusted the "Add Wound" modal to be full-screen on mobile devices for better usability.

[v1.0.0] - 2025-10-01

Initial Release

Core patient management (Add/Edit/Search).

Wound mapping and measurement tracking.

Basic SOAP note documentation.

User role management (Admin/Clinician).