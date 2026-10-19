<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/books.php';

/** Sends a JSON response and stops execution at the endpoint boundary. */
function detailsRespond(array $payload, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }

// Prefer the local normalized record so details remain available without an
// external request; only unsaved books are resolved through Hardcover.
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') detailsRespond(['error'=>'Method not allowed.'],405);
$id=filter_var($_GET['hardcover_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id===false)detailsRespond(['error'=>'A valid Hardcover ID is required.'],400);
try {
    $pdo=require __DIR__ . '/../settings/init.php'; $local=findSavedBookByHardcoverId($pdo,$id); if($local!==null)detailsRespond($local);
    $token=trim((string)getenv('HARDCOVER_API_TOKEN')); if($token==='')detailsRespond(['error'=>'The Hardcover API is not configured.'],503);
    detailsRespond(externalBookResponse(fetchHardcoverBook($id,$token)));
} catch (RuntimeException $e) { $status=$e->getCode(); if($status<400||$status>599)$status=500; detailsRespond(['error'=>$e->getMessage()],$status); }
catch (Throwable $e) { detailsRespond(['error'=>'Book details could not be loaded.'],500); }
