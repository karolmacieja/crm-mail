<?php

return [
    'contact_email_taken' => 'A contact with this email already exists in your CRM.',
    'phone_format' => 'The phone number may only contain digits, spaces and + ( ) . - / x.',
    'license_expired' => 'Your license expired on :date. Please renew to continue.',
    'license_missing' => 'No active license found for this account.',
    'admin_only' => 'This action requires administrator privileges.',
    'logged_out' => 'Logged out.',
    'wrong_client' => 'This endpoint is not available for this application.',
    'no_group' => 'This account is not assigned to any restaurant.',
    'group_inactive' => 'This restaurant account is inactive. Contact the administrator.',
    'use_web_panel' => 'Administrator accounts sign in to the web panel, not the Gmail extension.',
    'web_panel_admins_only' => 'Only the Master Admin can sign in to the web panel.',
    'license_seats_exceeded' => 'Seats cannot be lower than the number of assigned users (:used).',
    'group_delete_confirm' => 'Type the group slug (:slug) to confirm deleting it with all its data.',
    'cannot_delete_self' => 'You cannot delete your own account.',
    'master_admin_no_group' => 'A Master Admin cannot belong to a group.',
    'user_needs_group' => 'Choose a restaurant (group) for this user.',
    'custom_field_exists' => 'This contact already has a field ":label".',
    'settings' => [
        'last_item' => 'At least one entry must remain.',
        'in_use' => 'Used by :count records. Choose where to move them before deleting.',
    ],
    'calendar' => [
        'description' => 'Tasks, reminders and reservations of :name (GastroFlowx).',
        'task' => 'Task',
        'reminder' => 'Reminder',
        'reservation' => 'Reservation',
        'reservation_summary' => 'Reservation: :name, :count guests',
        'from_email' => 'From email: :subject',
    ],
];
