<?php

/*
| Polish validation messages for the rules used by this API.
| Rules not listed here fall back to lang/en/validation.php.
*/

return [
    'boolean' => 'Pole :attribute musi mieć wartość prawda lub fałsz.',
    'date' => 'Pole :attribute musi być poprawną datą.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'enum' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'exists' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'max' => [
        'array' => 'Pole :attribute może zawierać maksymalnie :max elementów.',
        'file' => 'Plik :attribute może mieć maksymalnie :max KB.',
        'numeric' => 'Pole :attribute nie może być większe niż :max.',
        'string' => 'Pole :attribute może mieć maksymalnie :max znaków.',
    ],
    'min' => [
        'array' => 'Pole :attribute musi zawierać co najmniej :min elementów.',
        'file' => 'Plik :attribute musi mieć co najmniej :min KB.',
        'numeric' => 'Pole :attribute musi być nie mniejsze niż :min.',
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
    ],
    'regex' => 'Format pola :attribute jest nieprawidłowy.',
    'required' => 'Pole :attribute jest wymagane.',
    'string' => 'Pole :attribute musi być tekstem.',
    'timezone' => 'Pole :attribute musi być poprawną strefą czasową.',
    'unique' => 'Taka wartość pola :attribute już istnieje.',

    'attributes' => [
        'contact_id' => 'kontakt',
        'days' => 'liczba dni',
        'device_name' => 'nazwa urządzenia',
        'direction' => 'kierunek sortowania',
        'due_date' => 'termin',
        'email' => 'e-mail',
        'is_completed' => 'wykonane',
        'name' => 'imię i nazwisko',
        'notes' => 'notatki',
        'page' => 'strona',
        'password' => 'hasło',
        'per_page' => 'liczba na stronę',
        'phone' => 'telefon',
        'regenerate_key' => 'nowy klucz',
        'search' => 'wyszukiwanie',
        'sort' => 'sortowanie',
        'status' => 'status',
        'title' => 'tytuł',
        'tz' => 'strefa czasowa',
    ],
];
