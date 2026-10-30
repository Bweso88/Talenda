<?php

use Illuminate\Support\Facades\Route;

// Pas de site vitrine pour l'instant : l'accueil mène au back-office des écoles.
Route::redirect('/', '/ecole');
