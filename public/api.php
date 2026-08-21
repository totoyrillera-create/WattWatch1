<?php
// public/api.php — public-facing API gateway
// JS fetch() calls hit this file; it loads the real controller from /src
require_once __DIR__ . '/../src/ApiController.php';
