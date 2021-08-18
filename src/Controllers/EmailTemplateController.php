<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class EmailTemplateController extends BaseController
{
  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokedata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;

    // Validate data
    $vResult = $this->vTemplate($data);

    // Add template
    if (empty($vResult)) {
      $userId = $this->getUserId($tokedata['uid']);
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('emailtemplates');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->subject = $data['subject'];
      $rb->content = $data['content'];      
      $rb->keys = $data['keys'];
      $rb->parent_id = 0;
      $rb->version_no = 0;
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "EMAIL_TEMPLATE_ADDED";
      $logArr = [ "user_id" => $tokedata['uid'] ];
      $resArr = ["uid" => $uid, "name" => $data['name'], "subject" => $data['subject'], "created_date" => $currentDate, "modified_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "EMAIL_TEMPLATE_VALIDATION_ERROR";
      $logArr = [ "user_id" => $tokedata['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function createVersion($id) {
    $ver = R::load('emailtemplates', $id);
    $uid = $this->getUid();

    $vb = R::findOne('emailtemplates', 'where parent_id = ? order by version_no desc', [$id]);    
    $verNo = empty($vb) ? 1 : $vb->version_no + 1;

    $rb = R::dispense('emailtemplates');
    $rb->uid = $uid;    
    $rb->name = $ver->name;
    $rb->subject = $ver->subject;
    $rb->content = $ver->content;
    $rb->keys = $ver->keys;
    $rb->parent_id = $id;
    $rb->version_no = $verNo;
    $rb->created_date = $ver->created_date;
    $rb->modified_date = $ver->modified_date;
    $rb->created_by = $ver->created_by;
    $rb->modified_by = $ver->modified_by;
    R::store($rb);
  }

  public function edit(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokedata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    $status = 500;
    $success = false;   
    
    
    // Update record
    if ($id) {
      $cArr = [];
      $rb = R::load('emailtemplates', $id);
      $name = strtolower($rb->name);
      $subject = strtolower($rb->subject);
      $content = strtolower($rb->content);
      $keys = strtolower($rb->keys);
      $createDate = $rb->created_date;

      if (isset($data['name']) && strtolower($data['name']) !== $name) {
        $rb->name = $data['name'];
        array_push($cArr, 'name');
      }
      if (isset($data['subject']) && strtolower($data['subject']) !== $subject) {
        $rb->subject = $data['subject'];
        array_push($cArr, 'subject');
      }
      if (isset($data['content']) && strtolower($data['content']) !== $content) {
        $rb->content = $data['content'];
        array_push($cArr, 'content');
      }
      if (isset($data['keys']) && strtolower($data['keys']) !== $keys) {
        $rb->keys = $data['keys'];
        array_push($cArr, 'keys');
      }

      // Validate data
      $vResult = $this->vTemplate($data, $cArr);

      if (empty($vResult)) {        
        if (!empty($cArr)) {
          $this->createVersion($id);
          $userId = $this->getUserId($tokedata['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          $success = true;
          $status = 201;
          $type = "EMAIL_TEMPLATE_UPDATED";
          $logArr = [ "user_id" => $tokedata['uid'], "template_id" => $data['uid'], "changes" => implode(", ", $cArr)];
          $resArr = ["uid" => $data['uid'] ,"name" => $data['name'], "subject" => $data['subject'], "created_date" => $createDate, "modified_date" => $currentDate, "changes" => $cArr ]; 
        } else {
          $status = 200;
          $type = "EMAIL_TEMPLATE_NO_CHANGES";
          $logArr = [ "user_id" => $tokedata['uid'], "template_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes were made template not updated" ]; 
        }
      } else {
        $status = 200;
        $type = "EMAIL_TEMPLATE_VALIDATION_ERROR";
        $logArr = [ "user_id" => $tokedata['uid'], "template_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "EMAIL_TEMPLATE_NOT_UPDATED";
      $logArr = [ "user_id" => $tokedata['uid'], "template_id" => $data['uid'] ];
      $resArr = [ "description" => "Invalid request recieved" ]; 
    }       
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function items(Request $request, Response $response) {
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $status = 500;
    $success = false;
    $records = R::findAll('emailtemplates', ' where parent_id = 0 order by name asc ');
    $row = [];
    foreach ($records as $record) {
      $row[] = [
        "uid" => $record->uid,
        "name" => $record->name,
        "subject" => $record->subject,
        "modified_date" => $record->modified_date
      ];
    }
    if (empty($row)) {
      $status = 200;
      $type = "EMAIL_TEMPLATES_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "EMAIL_TEMPLATES_RETRIVED";
      $resArr = $row; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  static function getTemplateByUID($args) {
    $result = false;
    if (isset($args['template_uid'])) {
      $rb = R::findOne('emailtemplates', 'uid = ?', [$args['template_uid']]);
      if (!empty($rb->id)) {
        $result = [ "id" => $rb->id, "name" => $rb->name]; 
      }
    }
    return $result;
  }

  public function list(Request $request, Response $response) {
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $status = 500;
    $success = false;
    $records = R::findAll('emailtemplates', ' where parent_id = 0 order by name asc ');
    $row = [];
    foreach ($records as $record) {
      $row[] = [
        "uid" => $record->uid,
        "name" => $record->name,
      ];
    }
    if (empty($row)) {
      $status = 200;
      $type = "EMAIL_TEMPLATE_LIST_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "EMAIL_TEMPLATE_LIST_RETRIVED";
      $resArr = $row; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function hasVersions($id) {
    $result = false;
    $rb = R::findAll('emailtemplates', 'where parent_id = ?', [$id]);
    if (!empty($rb)) {
      $result = count($rb);
    }
    return $result;
  }

  public function versions(Request $request, Response $response, $args) {
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $id = $this->getID($args);
    $status = 500;
    $success = false;

    if ($id) {
      $sql = "SELECT e.uid, e.name, e.subject, e.keys, e.parent_id, e.version_no, e.content, e.created_date, e.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM emailtemplates e ";
      $sql .= "LEFT JOIN users u1 ON e.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON e.modified_by = u2.id ";
      $sql .= "WHERE e.parent_id = :id " ;
      $sql .= "ORDER by e.version_no DESC";
      $rb = R::getAll($sql, [':id' => $id]);
    }
    
    if (empty($rb)) {
      $status = 200;
      $type = "EMAIL_TEMPLATE_VERSIONS_NOT_RETRIVED";
      $resArr = []; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "EMAIL_TEMPLATE_VERSIONS_RETRIVED";
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
    $tokedata = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('emailtemplates', $id);
      $name = $rb->name;
      R::trash( $rb );

      $rb = R::find('emailtemplates', 'parent_id = ?', [$id]);
      R::trashAll( $rb );

      $success = true;
      $status = 200;
      $type = "EMAIL_TEMPLATE_DELETED";
      $logArr = [ "user_id" => $tokedata['uid'],  "template_id" => $args['uid'], "template_name" => $name ];
      $resArr = [ "name" => $name ];
    } else{
      $status = 200;
      $type = "EMAIL_TEMPLATE_NOT_DELETED";
      $logArr = [ "user_id" => $tokedata['uid'],  "template_id" => $args['uid'], ];
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
    $tokedata = $this->getJwtTokenData($headers);

    $id = $this->getID($args);
    $status = 500;
    $success = false;

    
    if ($id) {
      $sql = "SELECT e.uid, e.name, e.subject, e.keys, e.content, e.created_date, e.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM emailtemplates e ";
      $sql .= "LEFT JOIN users u1 ON e.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON e.modified_by = u2.id ";
      $sql .= "WHERE e.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 
      $rb[0]['has_versions'] = $this->hasVersions($id);
      // $rb[0]['keys'] = explode(",", $rb[0]['keys']);

      $success = true;
      $status = 200;
      $type = "EMAIL_TEMPLATE_FOUND";
      $logArr = [ "user_id" => $tokedata['uid'],  "template_id" => $args['uid'], "template_name" => $rb[0]['name'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "EMAIL_TEMPLATE_NOT_FOUND";
      $logArr = [ "user_id" => $tokedata['uid'],  "template_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function duplicate(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokedata = $this->getJwtTokenData($headers);

    $oid = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($oid) {
      $userId = $this->getUserId($tokedata['uid']);
      $uid = $this->getUid();
      $org = R::load('emailtemplates', $oid);   
      $newName = $this->duplicateTemplateName($org->name);   
      $currentDate = date('Y-m-d H:i:s');
      $rb = R::dispense('emailtemplates');
      $rb->uid = $uid;
      $rb->name = $newName;
      $rb->subject = $org->subject;
      $rb->content = $org->content;
      $rb->keys = $org->keys;
      $rb->parent_id = 0;
      $rb->version_no = 0;
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "EMAIL_TEMPLATE_DUPLICATED";
      $logArr = [ "user_id" => $tokedata['uid'], "new_name" => $newName ];
      $resArr = ["created_date" => $currentDate, "modified_date" => $currentDate, "subject" => $org->subject, "uid" => $uid, "name" => $newName ]; 

    } else{
      $status = 200;
      $type = "EMAIL_TEMPLATE_NOT_DUPLICATED";
      $logArr = [ "user_id" => $tokedata['uid'],  "duplicated_from_id" => $args['uid'] ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function duplicateTemplateName($name, $count = 0){
    $newName = $name.' Copy'.(($count == 0) ? '' : ' '.$count);
    $rb  = R::findOne( 'emailtemplates', ' name LIKE ? ', [$newName]); 
    if (empty($rb->id)) {
      return $newName;
    } else {
      $count = $count + 1;
      return $this->duplicateTemplateName($name, $count);
    }
  }

  public function nameExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $name = $data['name'];

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'emailtemplates', 'name LIKE ? AND id != ?', [$name, $id]); 
    } else {
      $rb  = R::findOne( 'emailtemplates', 'name LIKE ?', [$name]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "EMAIL_TEMPLATE_NAME_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "EMAIL_TEMPLATE_NAME_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }
  
  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('emailtemplates', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function  vTemplate($data, $cArr = NULL) {    
    if (is_null($cArr) || isset($cArr['name'])) {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Template name is required",
          "isEmailNameExist" => "Template name already exist"
        ]
      ];
    } else {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Template name is required"
        ]
      ];
    }    
    $param['subject'] = [
      "value" => [
        "value" => isset($data['subject'])? $data['subject'] : '',
      ],      
      "type" => [ "isEmpty" => "Subject is required" ]
    ];
    $param['content'] = [
      "value" => [
        "value" => isset($data['content'])? $data['content'] : '',
      ],      
      "type" => [ "isEmpty" => "Content is required"]
    ];
    $param['keys'] = [
      "value" => [
        "value" => isset($data['keys'])? $data['keys'] : '',
      ],      
      "type" => [ "isEmpty" => "Keys is required"]
    ];
    
    return $this->validateData($param);
  }
}