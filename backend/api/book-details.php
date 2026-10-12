<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

$hardcoverId = filter_var(
  $_GET["hardcover_id"] ?? null,
  FILTER_VALIDATE_INT,
  ["options" => ["min_range" => 1]]
);

if ($hardcoverId === false || $hardcoverId === null) {
  http_response_code(400);

  echo json_encode(
    [
      "error" => "A valid Hardcover ID is required."
    ]
  );

  exit;
}
