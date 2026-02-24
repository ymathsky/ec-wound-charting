<?php
// Filename: ec/templates/global_styles.php
// This file contains a global stylesheet to enhance all form elements
// across the entire application for a consistent, modern UI/UX.
?>
<style>
    /* --- Global Form Element Enhancement --- */

    /* 1. Modern Text Inputs, Textareas, and Selects */
    input[type="text"],
    input[type="number"],
    input[type="date"],
    input[type="password"],
    input[type="email"],
    select,
    textarea {
        width: 100%;
        border-radius: 0.375rem; /* rounded-md */
        border: 1px solid #D1D5DB; /* border-gray-300 */
        padding: 0.5rem 0.75rem; /* py-2 px-3 */
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-color: #fff;
    }

    /* 2. Modern Focus State (Blue Ring) */
    input[type="text"]:focus,
    input[type="number"]:focus,
    input[type="date"]:focus,
    input[type="password"]:focus,
    input[type="email"]:focus,
    select:focus,
    textarea:focus {
        border-color: #4F46E5; /* indigo-600 */
        box-shadow: 0 0 0 2px #A5B4FC; /* ring-2 ring-indigo-300 */
        outline: none;
    }

    /* 3. Style Select Dropdown Arrow */
    select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }

    /* 4. Modern Checkboxes */
    input[type="checkbox"] {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        height: 1rem; /* h-4 */
        width: 1rem; /* w-4 */
        border: 1px solid #D1D5DB; /* border-gray-300 */
        border-radius: 0.25rem; /* rounded */
        cursor: pointer;
        display: inline-block;
        vertical-align: middle;
        position: relative;
        background-color: #fff;
        transition: all 0.1s ease-in-out;
    }

    input[type="checkbox"]:checked {
        background-color: #4F46E5; /* bg-indigo-600 */
        border-color: #4F46E5; /* border-indigo-600 */
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
        background-size: 100% 100%;
        background-position: center;
        background-repeat: no-repeat;
    }

    input[type="checkbox"]:focus {
        box-shadow: 0 0 0 2px #A5B4FC; /* ring-2 ring-indigo-300 */
        outline: none;
    }

    /* 5. Modern Radio Buttons */
    input[type="radio"] {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        height: 1rem; /* h-4 */
        width: 1rem; /* w-4 */
        border: 1px solid #D1D5DB; /* border-gray-300 */
        border-radius: 9999px; /* rounded-full */
        cursor: pointer;
        display: inline-block;
        vertical-align: middle;
        position: relative;
        background-color: #fff;
        transition: all 0.1s ease-in-out;
    }

    input[type="radio"]:checked {
        background-color: #4F46E5; /* bg-indigo-600 */
        border-color: #4F46E5; /* border-indigo-600 */
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3ccircle cx='8' cy='8' r='3'/%3e%3c/svg%3e");
        background-size: 100% 100%;
        background-position: center;
        background-repeat: no-repeat;
    }

    input[type="radio"]:focus {
        box-shadow: 0 0 0 2px #A5B4FC; /* ring-2 ring-indigo-300 */
        outline: none;
    }

    /* 6. Standard Form Label */
    .form-label {
        display: block;
        font-size: 0.875rem; /* text-sm */
        font-weight: 500; /* font-medium */
        color: #374151; /* text-gray-700 */
        margin-bottom: 0.25rem; /* mb-1 */
    }

    /* 7. Standard Form Input (Class you can use for consistency) */
    .form-input {
        /* All styles are already applied globally, but you can use this class */
        /* if you need to target inputs with JS, as we do in autosave_manager.js */
    }

</style>