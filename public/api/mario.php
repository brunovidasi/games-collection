<?php

/** The Super Mario checklist (the shape seed/mario.json has). */

require_once __DIR__ . '/../../includes/bootstrap.php';

send_api_json(encode_json(mario_json()));
