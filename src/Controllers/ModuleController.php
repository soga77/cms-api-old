<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \RedBeanPHP\R;

class ModuleController extends BaseController 
{

  public function add(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $status = 500;
    $success = false;
    
    // Validate data
    $vResult = $this->vModule($data);

    // Add template
    if (empty($vResult)) {
      $userId = $this->getUserId($tokendata['uid']);
      $currentDate = date('Y-m-d H:i:s');
      $uid = $this->getUid();
      $rb = R::dispense('modules');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->alias = $data['alias'];
      $rb->description = $data['description'];
      $rb->type = $data['type'];
      $rb->route = $data['route'];
      $rb->created_date = $currentDate;
      $rb->modified_date = $currentDate;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);

      $success = true;
      $status = 201;
      $type = "MODULE_ADDED";
      $logArr = [ "user_id" => $tokendata['uid'] ];
      $resArr = ["uid" => $uid, "name" => $data['name'], "alias" => $data['alias'], "description" => $data['description'], "type" => $data['type'], "modified_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "MODULE_VALIDATION_ERROR";
      $logArr = [ "user_id" => $tokendata['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  public function edit(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $id = $this->getID($data); //get record to update
    $status = 500;
    $success = false;    
    
    // Update record
    if ($id) {
      $arrC = [];
      $rb = R::load('modules', $id);
      $name = strtolower($rb->name);
      $alias = strtolower($rb->alias);
      $type = strtolower($rb->type);
      $route = strtolower($rb->route);
      $sortDescription = strtolower($rb->description);
      $createDate = $rb->created_date;

      if (isset($data['name']) && strtolower($data['name']) !== $name) {
        $rb->name = $data['name'];
        array_push($arrC, 'name');
      }
      if (isset($data['alias']) && strtolower($data['alias']) !== $alias) {
        $rb->alias = $data['alias'];
        array_push($arrC, 'alias');
      }
      if (isset($data['type']) && strtolower($data['type']) !== $type) {
        $rb->type = $data['type'];
        array_push($arrC, 'type');
      }
      if (isset($data['route']) && strtolower($data['route']) !== $route) {
        $rb->route = $data['route'];
        array_push($arrC, 'route');
      }
      if (isset($data['description']) && strtolower($data['description']) !== $sortDescription) {
        $rb->description = $data['description'];
        array_push($arrC, 'description');
      }
      // Validate data
      $vResult = $this->vModule($data, $arrC);

      if (empty($vResult)) {        
        if (!empty($arrC)) {
          $userId = $this->getUserId($tokendata['uid']);
          $currentDate = date('Y-m-d H:i:s');
          $rb->modified_date = $currentDate;
          $rb->modified_by = $userId;
          R::store($rb);

          $success = true;
          $status = 201;
          $type = "MODULE_UPDATED";
          $logArr = [ "user_id" => $tokendata['uid'], "module_id" => $data['uid'], "changes" => implode(", ", $arrC)];
          $resArr = ["uid" => $data['uid'] ,"name" => $data['name'], "alias" => $data['alias'], "type" => $data['type'], "description" => $data['description'], "created_date" => $createDate, "modified_date" => $currentDate, "changes" => $arrC ]; 
        } else {
          $status = 200;
          $type = "MODULE_NO_CHANGES";
          $logArr = [ "user_id" => $tokendata['uid'], "module_id" => $data['uid'] ];
          $resArr = [ "description" => "No changes made to module" ]; 
        }
      } else {
        $status = 200;
        $type = "MODULE_VALIDATION_ERROR";
        $logArr = [ "user_id" => $tokendata['uid'], "module_id" => $data['uid'], "validation" => $vResult ];
        $resArr = [ "validation" => $vResult ]; 
      }      
    } else {
      $status = 200;
      $type = "MODULE_NOT_UPDATED";
      $logArr = [ "user_id" => $tokendata['uid'], "module_id" => $data['uid'] ];
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

    $args = filter_var_array($request->getQueryParams(),FILTER_SANITIZE_STRING);

    $page = empty($args['page']) ? 1 : $args['page'];
    $itemsPerPage = empty($args['itemsPerPage']) ? 12 : $args['itemsPerPage'];

    $paramsC = [];
    $paramsF = [];

    $paramsF['sortBy'] = empty($args['sortBy']) ? 'name' : $args['sortBy'];
    $paramsF['sortDesc'] = empty($args['sortDesc']) ? 'ASC' : ($args['sortDesc'] === 'true' ? 'DESC' : 'ASC');
    if (!empty($args['search'])) {
      $paramsF['search'] = $args['search'];
      $paramsC['search'] = $args['search'];
    }
    $paramsF['offset'] = ($page - 1) * $itemsPerPage;
    $paramsF['rows'] = $itemsPerPage;

    

    $queryC = $this->itemsQuery($paramsC);
    $queryF = $this->itemsQuery($paramsF);    

    // var_dump($queryF);
    // die();

    $rec['count'] = count($queryC);
    $rec['items'] = $queryF;

    if (empty($queryF)) {
      $status = 200;
      $type = "MODULES_NOT_RETRIVED";
      $resArr = [ "count" => 0, "items" => []]; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "MODULES_RETRIVED";
      $resArr = $rec; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function itemsQuery($params) {
    $arr = [];

    $sql = "SELECT m.uid, m.name, m.alias, m.description, m.type, m.route, m.created_date, m.modified_date, COUNT(n.id) AS notifications, COUNT(p.id) AS permissions, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM modules m ";
    $sql .= "LEFT JOIN permissions p ON m.id = p.module_id ";
    $sql .= "LEFT JOIN notifications n ON m.id = n.module_id ";
    $sql .= "LEFT JOIN users u1 ON m.created_by = u1.id ";
    $sql .= "LEFT JOIN users u2 ON m.modified_by = u2.id ";
    if (!empty($params['search'])) {
      $arr[':search'] = $params['search'].'%';
      $sql.= "WHERE m.name LIKE :search OR m.alias LIKE :search OR m.description LIKE :search ";
    }
    $sql .= "GROUP BY m.id ";
    if (!empty($params['sortBy']) && !empty($params['sortDesc'])) {
      $sql .= "ORDER BY ".$params['sortBy']." ".$params['sortDesc']." ";
    }
    
    if (isset($params['offset']) && isset($params['rows'])) {
      $arr[':offset'] = $params['offset'];
      $arr[':rows'] = $params['rows'];
      $sql .= "LIMIT :offset, :rows ";
    } 
    
    

    if (empty($arr)) {
      $rb = R::getAll($sql); 
    } else {
      $rb = R::getAll($sql, $arr); 
    }

    return $rb; 
  }

  // public function items(Request $request, Response $response) {
  //   $headers = $request->getHeaders();
  //   $tokenData = $this->getJwtTokenData($headers);
  //   $status = 500;
  //   $success = false;

  //   // $sql = "SELECT m.uid, m.name, m.alias, m.description, m.type, m.route, m.created_date, m.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM modules m ";
  //   // $sql .= "LEFT JOIN users u1 ON m.created_by = u1.id ";
  //   // $sql .= "LEFT JOIN users u2 ON m.modified_by = u2.id ";
  //   // $sql .= "ORDER BY m.modified_date DESC";
  //   // $rb = R::getAll($sql); 

  //   $records = R::findAll('modules', 'order by name asc');
  //   $row = [];
  //   foreach ($records as $record) {
  //     $row[] = [
  //       "uid" => $record->uid,
  //       "name" => $record->name,
  //       "alias" => $record->alias,
  //       "type" => $record->type,
  //       "description" => $record->description,
  //       "modified_date" => $record->modified_date
  //     ];
  //   }


  //   if (empty($row)) {
  //     $status = 200;
  //     $type = "MODULES_NOT_RETRIVED";
  //     $resArr = []; 
  //     $logArr = [ "user_id" => $tokenData['uid'] ];      
  //   } else {
  //     $status = 200;
  //     $success = true;
  //     $type = "MODULES_RETRIVED";
  //     $resArr = $row; 
  //     $logArr = [ "user_id" => $tokenData['uid'] ];
  //   }

  //   // Return response
  //   $this->logger->info($type, $logArr);
  //   $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
  //   return $this->respondWithData($response,$result,$status);
  // }

  public function delete(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokendata = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $id = $this->getID($args);

    if ($id) {
      $rb = R::load('modules', $id);
      $name = $rb->name;
      R::trash( $rb );

      $rb = R::find('permissions', 'module_id = ?', [$id]);
      R::trashAll( $rb );

      $rb = R::find('notifications', 'module_id = ?', [$id]);
      R::trashAll( $rb );

      $success = true;
      $status = 200;
      $type = "MODULE_DELETED";
      $logArr = [ "user_id" => $tokendata['uid'],  "module_id" => $args['uid'], "module_name" => $name ];
      $resArr = [ "name" => $name ];
    } else{
      $status = 200;
      $type = "MODULE_NOT_DELETED";
      $logArr = [ "user_id" => $tokendata['uid'],  "module_id" => $args['uid'], ];
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
    $tokendata = $this->getJwtTokenData($headers);

    $id = $this->getID($args);
    $status = 500;
    $success = false;
    
    if ($id) {
      $sql = "SELECT m.uid, m.name, m.alias, m.description, m.type, m.route, m.created_date, m.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM modules m ";
      $sql .= "LEFT JOIN users u1 ON m.created_by = u1.id ";
      $sql .= "LEFT JOIN users u2 ON m.modified_by = u2.id ";
      $sql .= "WHERE m.id = :id";

      $rb = R::getAll($sql, [':id' => $id]); 
      $success = true;
      $status = 200;
      $type = "MODULE_FOUND";
      $logArr = [ "user_id" => $tokendata['uid'],  "module_id" => $args['uid'], "module_name" => $rb[0]['name'] ];
      $resArr = $rb[0];
    } else{
      $status = 200;
      $type = "MODULE_NOT_FOUND";
      $logArr = [ "user_id" => $tokendata['uid'],  "module_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }  

  public function nameExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $name = $data['name'];

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'modules', 'name LIKE ? AND id != ?', [$name, $id]); 
    } else {
      $rb  = R::findOne( 'modules', 'name LIKE ?', [$name]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "MODULE_NAME_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "MODULE_NAME_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  public function aliasExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $id = $this->getID($data); 
    $alias = $data['alias'];

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'modules', 'alias LIKE ? AND id != ?', [$alias, $id]); 
    } else {
      $rb  = R::findOne( 'modules', 'alias LIKE ?', [$alias]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "MODULE_ALIAS_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "MODULE_ALIAS_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  static function getModuleByUID($args) {
    $result = false;
    if (isset($args['module_uid'])) {
      $rb = R::findOne('modules', 'uid = ?', [$args['module_uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    } elseif (isset($args['uid'])) {
      $rb = R::findOne('modules', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }
    return $result;
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('modules', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }


  private function  vModule($data, $arrC = NULL) {    
    if (is_null($arrC) || isset($arrC['name'])) {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Name is required",
          "isModuleNameExist" => "Name already exist"
        ]
      ];
    } else {
      $param['name'] = [
        "value" => [
          "value" => isset($data['name'])? $data['name'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Name is required"
        ]
      ];
    }    
    if (is_null($arrC) || isset($arrC['alias'])) {
      $param['alias'] = [
        "value" => [
          "value" => isset($data['alias'])? $data['alias'] : '',
        ],        
        "type" => [ 
          "isEmpty" => "Alias is required",
          "isNotAlias" => "Invalid alias format",
          "isModuleAliasExist" => "Alias already exist"
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
    $param['type'] = [
      "value" => [
        "value" => isset($data['type'])? $data['type'] : '',
      ],
      "type" => [ "isEmpty" => "Module type is required" ]
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