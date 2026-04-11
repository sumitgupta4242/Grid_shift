<?php
// api/device_toggle.php – AJAX device toggle / add / delete
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Device.php';
if (empty($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$mgr    = new Device($db, $userId);
$action = $_POST['action'] ?? $_GET['action'] ?? 'toggle';

switch ($action) {
    case 'toggle':
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode($mgr->toggle($id));
        break;

    case 'add':
        $name     = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $priority = (int)($_POST['priority'] ?? 5);
        $watts    = (int)($_POST['watts'] ?? 100);
        $essential= (bool)($_POST['essential'] ?? false);
        $icon     = trim($_POST['icon'] ?? '🔌');
        if (!$name) { echo json_encode(['success'=>false,'message'=>'Name required']); break; }
        $newId = $mgr->add($name, $category, $priority, $watts, $essential, $icon);
        echo json_encode(['success'=>true,'id'=>$newId]);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $dev = $mgr->getOne($id);
        if ($dev && $dev['is_essential']) {
            echo json_encode(['success'=>false,'message'=>'Cannot delete essential device']);
        } else {
            echo json_encode(['success'=>$mgr->delete($id)]);
        }
        break;

    case 'priority':
        $id       = (int)($_POST['id'] ?? 0);
        $priority = (int)($_POST['priority'] ?? 5);
        echo json_encode(['success'=>$mgr->updatePriority($id,$priority)]);
        break;

    case 'load':
        echo json_encode(['load_kw'=>$mgr->totalLoadKw()]);
        break;

    default:
        echo json_encode(['error'=>'Unknown action']);
}
