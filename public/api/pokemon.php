<?php

/** The Pokémon checklist (the shape seed/pokemon.json has). */

require_once __DIR__ . '/../../includes/bootstrap.php';

send_api_json(encode_json(pokemon_json()));
