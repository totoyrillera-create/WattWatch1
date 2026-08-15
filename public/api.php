<?php
// public/api.php — Public-facing API gateway
// All JS fetch() calls go here. This file is inside /public so the web server can serve it.
// It simply loads ApiController.php from /src which does all the real work.

require_once __DIR__ . '/../src/ApiController.php';
