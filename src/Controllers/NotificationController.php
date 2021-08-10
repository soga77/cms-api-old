<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class NotificationController extends BaseController 
{
  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;
    
    // Validate data
    $vResult = $this->vNotification($data);

    // Add template
    if (empty($vResult)) {
      $userId = $this->getUserId($auth['uid']);
      $moduleID = $this->getModuleID($data);
      $templateArr = $this->getTemplateID($data);
      $templateID = $templateArr['id'];
      $templateName = $templateArr['name'];
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('notifications');
      $rb->uid = $uid;
      $rb->module_id = $moduleID;
      $rb->template_id = $templateID;
      $rb->alias = $data['alias'];
      $rb->description = $data['description'];
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "NOTIFICATION_ADDED";
      $logArr = [ "user_id" => $auth['uid'] ];
      $resArr = ["uid" => $uid, "template_name" => $templateName, "alias" => $data['alias'], "modified_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "NOTIFICATION_VALIDATION_ERROR";
      $logArr = [ "user_id" => $auth['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function getNotifications($id) {
    $rb  = R::find( 'notifications', ' module_id = ? order by alias asc', [$id]); 
    foreach ($rb as $key => $value) {
      unset($rb[$key]['id']);
      unset($rb[$key]['module_id']);
    }
    if (empty($rb)) {
      $result = [];
    } else {
      $result = $rb;
    }
    return $result;
  }

  // private function notifications($id, $notifications) {
  //   foreach ($notifications as $notification) {
  //     if ($notification['action'] === 'add') {
  //       $this->notificationAdd($id, $notification);
  //     } elseif ($notification['action'] === 'edit') {
  //       $this->notificationEdit($id, $notification);
  //     } elseif ($notification['action'] === 'delete') {
  //       $this->notificationDelete($id, $notification);
  //     }
  //   }
  // }

  // private function notifications($id, $notifications) {
  //   $result = false;    
  // }



  public function edit(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    $status = 500;
    $success = false;    
    
    // Update record
    if ($id) {
      $cArr = [];
      $rb = R::load('notifications', $id);
      $alias = strtolower($rb->alias);
      $description = strtolower($rb->description);
      $moduleID = $this->getModuleID($data);
      $templateArr = $this->getTemplateID($data);
      $templateID = $templateArr['id'];
      $templateName = $templateArr['name'];

      if ($moduleID !== $rb->module_id) {
        $rb->module_id = $moduleID;
        array_push($cArr, 'module_id');
      }
      if ($templateID !== $rb->template_id) {
        $rb->template_id = $templateID;
        array_push($cArr, 'template_id');
      }
      if (isset($data['alias']) && strtolower($data['alias']) !== $alias) {
        $rb->alias = $data['alias'];
        array_push($cArr, 'alias');
      }
      if (isset($data['description']) && strtolower($data['description']) !== $description) {
        $rb->description = $data['description'];
        array_push($cArr, 'description');
      }
      // Validate data
      $vResult = $this->vNotification($data, $cArr);

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $userId = $this->getUserID($auth['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          $success = true;
          $status = 201;
          $type = "NOTIFICATION_UPDATED";
          $logArr = [ "user_id" => $auth['uid'], "notification_id" => $data['uid'], "changes" => implode(", ", $cArr)];
          $resArr = ["uid" => $data['uid'] , "template_name" => $templateName, "alias" => $data['alias'], "modified_date" => $currentDate, "changes" => $cArr ];
        } else {
          $status = 200;
          $type = "NOTIFICATION_NO_CHANGES";
          $logArr = [ "user_id" => $auth['uid'], "notification_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes made to notification" ]; 
        }
      } else {
        $status = 200;
        $type = "NOTIFICATION_VALIDATION_ERROR";
        $logArr = [ "user_id" => $auth['uid'], "notification_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "NOTIFICATION_NOT_UPDATED";
      $logArr = [ "user_id" => $auth['uid'], "notification_id" => $data['uid'] ];
      $resArr = [ "description" => "Invalid request recieved" ]; 
    }       
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function items(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);

    $id = $this->getModuleID($args);
    $status = 500;
    $success = false;

    $sql = "SELECT n.uid, n.alias, n.modified_date, m.name as module_name, e.name as template_name FROM notifications n ";
    $sql .= "LEFT JOIN emailtemplates e ON n.template_id = e.id ";
    $sql .= "LEFT JOIN modules m ON n.module_id = m.id ";
    $sql .= "WHERE n.module_id = :id ORDER by alias asc ";

    

    $rb = R::getAll($sql, [':id' => $id]); 

    

    // $records = R::findAll('notifications', 'module_id = ? order by alias asc ', [$id]);
    // var_dump($records);
    // die();

    // $row = [];
    // foreach ($records as $record) {
    //   $row[] = [
    //     "uid" => $record->uid,
    //     // "module_id" => $record->module_id,
    //     "alias" => $record->alias,
    //     // "description" => $record->description,
    //     "modified_date" => $record->modified_date
    //   ];
    // }

    

    if (empty($rb)) {
      $status = 200;
      $type = "PERMISSIONS_NOT_RETRIVED";
      $resArr = [];
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "PERMISSIONS_RETRIVED";
      $resArr = $rb; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function delete(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('notifications', $id);
      $alias = $rb->alias;
      R::trash( $rb );

      $success = true;
      $status = 200;
      $type = "NOTIFICATION_DELETED";
      $logArr = [ "user_id" => $auth['uid'],  "notification_id" => $args['uid'], "notification_alias" => $alias ];
      $resArr = [ "alias" => $alias ];
    } else{
      $status = 200;
      $type = "NOTIFICATION_NOT_DELETED";
      $logArr = [ "user_id" => $auth['uid'],  "notification_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function item(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $auth = $this->getJwtTokenData($headers);

    $id = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($id) {
      $sql = "SELECT n.uid, n.alias, n.description, n.created_date, n.modified_date, m.name as module_name, e.uid as template, e.name as template_name, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM notifications n ";
      $sql .= "LEFT JOIN emailtemplates e ON n.template_id = e.id ";
      $sql .= "LEFT JOIN modules m ON n.module_id = m.id ";
      $sql .= "LEFT JOIN users u1 ON n.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON n.modified_by = u2.id ";
      $sql .= "WHERE n.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 
      $success = true;
      $status = 200;
      $type = "NOTIFICATION_FOUND";
      $logArr = [ "user_id" => $auth['uid'],  "notification_id" => $args['uid'], "notification_alias" => $rb[0]['alias'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "NOTIFICATION_NOT_FOUND";
      $logArr = [ "user_id" => $auth['uid'],  "notification_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }  

  public function aliasExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $alias = $data['alias'];
    $moduleID = $this->getModuleID($data);

    $status = 500;
    $success = false;

    // $rb  = R::findOne( 'notifications', ' module_id = ? AND alias LIKE ? ', [$value['value']['id']

    if ($id) {
      $rb  = R::findOne( 'notifications', 'module_id = ? AND alias LIKE ? AND id != ?', [$moduleID, $alias, $id]); 
    } else {
      $rb  = R::findOne( 'notifications', 'module_id = ? AND alias LIKE ?', [$moduleID, $alias]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "NOTIFICATION_ALIAS_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "NOTIFICATION_ALIAS_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('notifications', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function  vNotification($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['alias'])) {
      $moduleID = $this->getModuleID($data);
      $param['alias'] = [
        "value" => [
          "id" => $moduleID,
          "value" => isset($data['alias'])? $data['alias'] : '',
        ],
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotAlias" => "Invalid alias format",
          "isNotificationAliasExist" => "Alias already exist"
        ]
      ];
    } else {
      $param['alias'] = [
        "value" => [
          "value" => isset($data['alias'])? $data['alias'] : '',
        ],
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotAlias" => "Invalid alias format",
        ]
      ];
    }    
    $param['module_uid'] = [
      "value" => [
        "value" => isset($data['module_uid'])? $data['module_uid'] : '',
      ],
      "type" => [ "isEmpty" => "Module selection is required" ]
    ];
    $param['template_uid'] = [
      "value" => [
        "value" => isset($data['template_uid'])? $data['template_uid'] : '',
      ],
      "type" => [ "isEmpty" => "Email template selection is required" ]
    ];
    $param['description'] = [
      "value" => [
        "value" => isset($data['description'])? $data['description'] : '',
      ],
      "type" => [ "isEmpty" => "Description is required"]
    ];

    return $this->validateData($param);
  }
}