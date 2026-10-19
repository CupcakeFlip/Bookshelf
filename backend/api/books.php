<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/books.php';

/**
 * Sends a JSON response and terminates the endpoint so no later branch can
 * accidentally append output to the response body.
 *
 * @param array $payload JSON-serializable response data.
 * @param int $status HTTP status code returned to the client.
 * @return never
 */
function respond(array $payload, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }

// GET reads locally saved books; DELETE removes only the user's status row;
// POST creates or updates that row and fetches shared metadata when necessary.
$pdo=require __DIR__ . '/../settings/init.php'; $method=$_SERVER['REQUEST_METHOD']??'GET';
if ($method==='GET') { $q=trim((string)($_GET['q']??'')); $status=trim((string)($_GET['status']??'')); $allowed=['want_to_read','reading','finished','paused','dnf']; if ($status!==''&&!in_array($status,$allowed,true)) respond(['error'=>'status is invalid.'],422); $sql='SELECT DISTINCT b.id FROM books b JOIN user_books ub ON ub.book_id=b.id LEFT JOIN book_authors ba ON ba.book_id=b.id LEFT JOIN authors a ON a.id=ba.author_id'; $conditions=[]; $params=[]; if ($q!=='') {$conditions[]='(b.title LIKE ? OR a.name LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%';} if ($status!=='') {$conditions[]='ub.status=?'; $params[]=$status;} if ($conditions) $sql.=' WHERE '.implode(' AND ',$conditions); $sql.=' ORDER BY COALESCE(ub.updated_at, "1970-01-01") DESC, b.id'; $s=$pdo->prepare($sql); $s->execute($params); $out=[]; foreach($s->fetchAll() as $row)$out[]=savedBook($pdo,(int)$row['id']); respond($out); }
if ($method==='DELETE') { $id=filter_var($_GET['hardcover_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id===false)respond(['error'=>'hardcover_id must be a positive integer.'],400); $bookId=sessionBookId($pdo,$id); if($bookId===null)respond(['error'=>'Book not found.'],404); $pdo->beginTransaction(); try { transitionSession($pdo,$bookId,'want_to_read'); $pdo->prepare('DELETE FROM user_books WHERE book_id=?')->execute([$bookId]); $pdo->commit(); } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack(); respond(['error'=>'The book could not be removed from the library.'],500);} respond(['status'=>null]); }
if ($method!=='POST') respond(['error'=>'Method not allowed.'],405);
$payload=json_decode((string)file_get_contents('php://input'),true); if(!is_array($payload)||!array_key_exists('hardcover_id',$payload))respond(['error'=>'A JSON body with hardcover_id is required.'],400); $id=filter_var($payload['hardcover_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id===false)respond(['error'=>'hardcover_id must be a positive integer.'],400); $status=$payload['status']??'want_to_read'; if(!is_string($status)||!in_array($status,['want_to_read','reading','finished','paused','dnf'],true))respond(['error'=>'status is invalid.'],422); $details=is_array($payload['session']??null)?$payload['session']:[];
$existing=findSavedBookByHardcoverId($pdo,$id); if($existing){$bookId=(int)$existing['id'];$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO user_books (book_id,status) VALUES (?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)')->execute([$bookId,$status]);transitionSession($pdo,$bookId,$status,$details);$pdo->commit();}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();respond(['error'=>$e->getMessage()],422);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();respond(['error'=>'The book status could not be updated.'],500);}respond(savedBook($pdo,$bookId));}
$token=trim((string)getenv('HARDCOVER_API_TOKEN')); if($token==='')respond(['error'=>'The Hardcover API is not configured.'],503); try{respond(saveBook($pdo,fetchHardcoverBook($id,$token),$status,$details),201);}catch(RuntimeException $e){respond(['error'=>$e->getMessage()],$e->getCode()>=400?$e->getCode():500);}catch(Throwable $e){respond(['error'=>'The book could not be saved.'],500);}
