<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$session = session_id();

$stmt = $db->prepare("UPDATE appointment SET appointment_patient_name = :name, appointment_patient_session = :session, appointment_status = 'waiting' WHERE appointment_id = :id");
$stmt->bindParam(':id', $params->id);
$stmt->bindParam(':name', $params->name);
$stmt->bindParam(':session', $session);
$stmt->execute();

class Result {}

$response = new Result();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);
