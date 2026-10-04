<?php

return [
    'contact_email_taken' => 'Kontakt z tym adresem e-mail już istnieje w Twoim CRM.',
    'phone_format' => 'Numer telefonu może zawierać tylko cyfry, spacje oraz znaki + ( ) . - / x.',
    'license_expired' => 'Twoja licencja wygasła :date. Odnów ją, aby kontynuować.',
    'license_missing' => 'To konto nie ma aktywnej licencji.',
    'admin_only' => 'Ta operacja wymaga uprawnień administratora.',
    'logged_out' => 'Wylogowano.',
    'wrong_client' => 'Ten endpoint nie jest dostępny dla tej aplikacji.',
    'no_group' => 'To konto nie jest przypisane do żadnej restauracji.',
    'group_inactive' => 'Konto tej restauracji jest nieaktywne. Skontaktuj się z administratorem.',
    'use_web_panel' => 'Konta administratora logują się w panelu webowym, a nie w rozszerzeniu Gmail.',
    'web_panel_admins_only' => 'Do panelu webowego może zalogować się tylko Master Admin.',
    'license_seats_exceeded' => 'Liczba miejsc nie może być mniejsza niż liczba przypisanych użytkowników (:used).',
    'group_delete_confirm' => 'Wpisz identyfikator grupy (:slug), aby potwierdzić usunięcie jej wraz ze wszystkimi danymi.',
    'cannot_delete_self' => 'Nie możesz usunąć własnego konta.',
    'master_admin_no_group' => 'Master Admin nie może należeć do grupy.',
    'user_needs_group' => 'Wybierz restaurację (grupę) dla tego użytkownika.',
    'custom_field_exists' => 'Ten kontakt ma już pole „:label”.',
    'settings' => [
        'last_item' => 'Musi zostać co najmniej jedna pozycja.',
        'in_use' => 'Używane przez :count rekordów. Wybierz, dokąd je przenieść przed usunięciem.',
    ],
    'calendar' => [
        'description' => 'Zadania, przypomnienia i rezerwacje: :name (GastroFlowx).',
        'task' => 'Zadanie',
        'reminder' => 'Przypomnienie',
        'reservation' => 'Rezerwacja',
        'reservation_summary' => 'Rezerwacja: :name, gości: :count',
        'from_email' => 'Z maila: :subject',
    ],

    // Private contacts and sharing.
    'sharing' => [
        'owner_only' => 'Tylko opiekun klienta może zmieniać jego dane i udostępnianie.',
        'read_only' => 'To wpis innej osoby – możesz go tylko przeglądać.',
        'email_owned_by' => 'Ten klient jest już w CRM – jego opiekunem jest :name. Poproś o dostęp z karty klienta w Gmailu.',
        'already_owner' => 'Ta osoba jest już opiekunem klienta.',
        'nothing_selected' => 'Zaznacz, co chcesz udostępnić.',
        'already_has_access' => 'Masz już pełny dostęp do tego klienta.',
        'request_closed' => 'Ta prośba została już rozpatrzona.',
    ],

    // Business correspondence: private category with personal cards.
    'personal' => [
        'already_have_card' => 'Masz już własną kartę tego kontaktu.',
        'other_cards' => 'Inne osoby mają własne karty tego kontaktu (korespondencja firmowa) – nie można go przenieść do kategorii wspólnej.',
        'not_shared' => 'Karty z kategorii prywatnej (korespondencja firmowa) nie są udostępniane. Możesz dodać wybrane maile do wspólnej puli.',
        'pool_only_personal' => 'Do wspólnej puli trafiają maile z prywatnych kart korespondencji firmowej.',
        'category_in_use' => 'Ta prywatna kategoria ma przypisane kontakty. Najpierw wyłącz jej prywatność albo przenieś kontakty.',
        'cannot_unprivate' => 'Nie można wyłączyć prywatności: kilka osób ma własne karty tych samych kontaktów (:count). Najpierw usuńcie zbędne karty.',
    ],
];
