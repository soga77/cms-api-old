<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface as UploadedFile;
use Psr\Container\ContainerInterface;
use \RedBeanPHP\R;

class FileManagerController extends BaseController 
{
  public function image (Request $request, Response $response, $args) {
    $fileName = $args['slug'];
    $fileSize = $args['size'];
    $rb  = R::findOne( 'filemanager', 'slug = ?', [$fileName]);
    $fileType = $fileSize === "og" ? $rb->mime : $this->fileManager['outputFile']['type'];
    
    if(!empty($rb)) {
      $getPath = $this->getPath($rb->parent_id);
      $filePath = $getPath.'/'.$fileSize.'-'.$rb->slug;
    } else {
      $filePath = __DIR__ . $this->fileManager['path']. 'image-not-found.jpg';
    }
    
    if (!file_exists($filePath)) {
      die("file:$filePath");
    }

    $image = file_get_contents($filePath);

    if ($image === false) {
        die("error getting image");
    }
    $response->getBody()->write($image);
    return $response->withHeader('Content-Type', $fileType);
  }

  public function items(Request $request, Response $response) {
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $status = 500;
    $success = false;    

    $args = filter_var_array($request->getQueryParams(),FILTER_SANITIZE_STRING);

    $page = empty($args['page']) ? 1 : $args['page'];
    $itemsPerPage = empty($args['itemsPerPage']) ? 12 : $args['itemsPerPage'];
    $id = $this->getID($args);
    // var_dump($id);
    $folder = $id ? $id : 0;    

    $paramsC = [];
    $paramsF = [];

    $paramsF['folder'] = $folder;
    $paramsC['folder'] = $folder;

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

    $rec['puid'] = $this->getPUID($id);
    $rec['breadcrumbs'] = $this->getBreadcrumbs($id);
    $rec['count'] = count($queryC);
    $rec['items'] = empty($queryF) ? [] : $queryF;

    if (empty($queryF)) {
      $status = 200;
      $type = "FILES_NOT_RETRIVED";
      $resArr = $rec; 
      $logArr = [ "user_id" => $tokenData['uid'] ];      
    } else {
      $status = 200;
      $success = true;
      $type = "FILES_RETRIVED";
      $resArr = $rec; 
      $logArr = [ "user_id" => $tokenData['uid'] ];
    }

    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function itemsQuery($params) {
    // $arr = [];
    $arr[':folder'] = $params['folder'];

    $sql = "SELECT f.uid, f.name, f.slug, f.type, f.created_date, f.modified_date, CONCAT(u1.first_name,' ',u1.last_name) as created_by, CONCAT(u2.first_name,' ',u2.last_name) as modified_by  FROM filemanager f ";
    $sql .= "LEFT JOIN users u1 ON f.created_by = u1.id ";
    $sql .= "LEFT JOIN users u2 ON f.modified_by = u2.id ";
    $sql.= "WHERE f.parent_id = :folder ";
    if (!empty($params['search'])) {
      $arr[':search'] = $params['search'].'%';
      $sql.= "AND f.name LIKE :search ";
    }
    // $sql .= "GROUP BY f.type, f.id ";
    if (!empty($params['sortBy']) && !empty($params['sortDesc'])) {
      $sql .= "ORDER BY FIELD(type, 'folder') DESC, ".$params['sortBy']." ".$params['sortDesc']." ";
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

  public function addFolder(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);

    $data = $request->getParsedBody();
    $data['slug'] = isset($data['name']) ? $this->slugify($data['name']) : '';
    $cid = $this->getID($data);
    $data['parent_id'] = $cid ? $cid : 0;
    $status = 500;
    $success = false;

    // Validate data
    $vResult = $this->vFolder($data);

    if (empty($vResult)) {
      $userId = $this->getUserId($tokenData['uid']);
      $currentDate = date('Y-m-d H:i:s');

      $uid = $this->getUid();
      $rb = R::dispense('filemanager');
      $rb->uid = $uid;
      $rb->name = $data['name'];
      $rb->slug = $data['slug'];
      $rb->type = "folder";
      $rb->parent_id = $data['parent_id'];
      $rb->created_date = $currentDate;
      $rb->modified_date = null;
      $rb->created_by = $userId;
      $rb->modified_by = null;
      $id = R::store($rb);
      $path = $this->getPath($id);

      if(!is_dir($path)) {
        mkdir($path,0777,TRUE);
      }     

      $success = true;
      $status = 201;
      $type = "FOLDER_ADDED";
      $logArr = [ "owner" => $tokenData['uid'], "folder_uid" => $uid ];
      $resArr = ["uid" => $uid, "name" => $data['name'], "parent_id" => $data['parent_id'], "path" => $path, "created_date" => $currentDate];     
    } 
    // Set validation error(s)
    else {
      $status = 200;
      $type = "ROLE_VALIDATION_ERROR";
      $logArr = [ "owner" => $tokenData['uid'],  "validation" => $vResult ];
      $resArr = [ "validation" => $vResult ];
    }
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getBreadcrumbs($id) {
    
    $disabled = true;

    $arr = $this->breadcrumbArr($id, [], $disabled);
    return $arr;
  }

  private function breadcrumbArr($id, array $arr=[], $disabled=false) {
    if ($id) {
      $bc = R::load( 'filemanager', $id );# code...
    } else {
      $disabled=true;
      $bc  = R::findOne( 'filemanager', 'parent_id = 0' );
    }

    if ($bc) {
      array_unshift($arr, [ "uid" => $bc['uid'], "disabled" => $disabled, "text" => $bc['name'], "slug" => $bc['slug'] ]);
    
      if ($bc['parent_id'] != 0) {
        $arr = $this->breadcrumbArr($bc['parent_id'], $arr);
      }
    }    
    return $arr;
  }

  private function getPath($id) {
    $arr = $this->getFolderPath($id);
    $path = __DIR__ .$this->fileManager['path'].implode("/",$arr);
    return $path;
  }

  private function getFolderPath($id, array $arr=[]) {
    $fd = R::load( 'filemanager', $id );
    array_unshift($arr, $fd['slug']);

    if ($fd['parent_id'] != 0) {
      $arr = $this->getFolderPath($fd['parent_id'], $arr);
    }
    
    return $arr;
  }

  public function uploadFile(Request $request, Response $response) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);
    $data = $request->getParsedBody();
    $uploadedFiles = $request->getUploadedFiles();
    $status = 500;

    $pid = $this->getPID($data) ? $this->getPID($data) : 0;
    $uploadPath = $this->getPath($pid);
    $uploadedFile = $uploadedFiles['file'];

    // move_uploaded_file($uploadFile['file'], $uploadPath);
    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {      
      $fileSize = $uploadedFile->getSize();
      $clientFileType = $uploadedFile->getClientMediaType();
      $fileTypeArr = explode("/", $clientFileType);
      $fileType = $fileTypeArr[0];
      // var_dump($fileSize." ".$fileSettings['maxSize']);
      // check if fiile type is allowed
      if (!in_array($clientFileType,$this->fileManager['allowedType'])) {
        $status = 403;
        $payload =  "File type not allowed";
      } elseif ($fileSize > $this->fileManager['maxSize']) {
        $status = 403;
        $payload =  "File exceeds allowable file size";
      } else {
        $status = 200;
        // $fileName = $this->moveUploadedFile($uploadPath, $uploadedFile);
        $fileName = $this->moveUploadedImageFile($uploadPath, $uploadedFile);        
        $userId = $this->getUserId($tokenData['uid']);
        $currentDate = date('Y-m-d H:i:s');
        // $slug = $this->slugify($clientFileName);

        $uid = $this->getUid();
        $rb = R::dispense('filemanager');
        $rb->uid = $uid;
        $rb->name = $fileName;
        $rb->slug = $fileName;
        $rb->size = $fileSize;
        $rb->mime = $clientFileType;
        $rb->type = $fileType;
        $rb->parent_id = $pid;
        $rb->created_date = $currentDate;
        $rb->modified_date = null;
        $rb->created_by = $userId;
        $rb->modified_by = null;
        R::store($rb);

        $payload =  "File Uploaded"; 
      }
      
      
    } else {
      $payload =  "File not uploaded";
    }
    
    return $this->respondWithString($response,$payload,$status);
  }

  private function moveUploadedImageFile($directory, UploadedFile $uploadedFile)
  {
    $arr = pathinfo($uploadedFile->getClientFilename());
    $name = $this->slugify($arr['filename']);
    $fileName = $this->getNewFilename($name, $this->fileManager['outputFile']['ext']);   
    $fileNameOrgExt = $this->getNewFilename($name, $arr['extension']);   
    
    $imageFile = (string)$uploadedFile->getStream();
    $imageType = imagecreatefromstring($imageFile);
    list($imgWidth, $imgHeight) = getimagesizefromstring($imageFile);
    $imgRatio = $imgWidth/$imgHeight;

    foreach ($this->fileManager['imageSize'] as $img) {
      $width = $img['width'];
      $height = $img['height']; 

      // $newFilePath = $directory. DIRECTORY_SEPARATOR .$img['prefix'].$fileName;

      // if ($imgWidth > $imgHeight) {
      //   $newWidth=$width;
      //   $newHeight=ceil($imgRatio*$newWidth);
      // }
      // elseif ($imgWidth < $imgHeight) {
      //   $newHeight=$height;
      //   $newWidth=ceil($imgRatio*$newHeight);
      // }
      // elseif ($imgWidth == $imgHeight) {
      //   $newWidth=$height;
      //   $newHeight=$height;
      // }
      
      // // determine offset coords so that new image is centered
      // $offestX = ceil(($width - $newWidth) / 2);
      // $offestY = ceil(($height - $newHeight) / 2);
        
      // $tmpImg=imagecreatetruecolor($width,$height);
      // imagesavealpha($tmpImg, true);
  
      // // Create some colors
      // $color = imagecolorallocatealpha($tmpImg,0x00,0x00,0x00,127); 
      // imagefill($tmpImg, 0, 0, $color); 
      
      // imagecopyresampled($tmpImg,$imageType,$offestX,$offestY,0,0,$newWidth,$newHeight,$imgWidth,$imgHeight);
      
      // imagealphablending($tmpImg, false);
      // imagesavealpha($tmpImg, true);
      // imagejpeg($tmpImg,$newFilePath,90);

      // imagepng($tmpImg,$newFilePath,9);

      $newFilePath = $directory. DIRECTORY_SEPARATOR .$img['prefix'].$fileName;
    
      if ($width/$height > $imgRatio) {
        $width = $height*$imgRatio;
      } else {
        $height = $width/$imgRatio;
      }
			
		  $tmpImg=imagecreatetruecolor($width,$height);		
		  imagecopyresampled($tmpImg,$imageType,0,0,0,0,$width,$height,$imgWidth,$imgHeight);
      imagejpeg($tmpImg,$newFilePath,90);
    }

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . 'og-' .$fileNameOrgExt); 
    imagedestroy($imageType);
    return $fileName;
  }

  public function delete(Request $request, Response $response, $args) {
    // Authorization
    $headers = $request->getHeaders();
    $tokenData = $this->getJwtTokenData($headers);

    $status = 500;
    $success = false;

    $rb = $this->getRecByUid($args);

    if ($rb) {
      // $rb = R::load('filemanager', $id);
      $id = $rb->id;
      $name = $rb->name;
      $type = $rb->type;
      $pid = $rb->parent_id;
      $slug = $rb->slug;
      $sizeArr = [ 'sm-', 'md-', 'lg-', 'og-' ];
      $getPath = $this->getPath($pid);

      if ($type === 'folder') {
        $rbc = R::findAll('filemanager', ' where parent_id = ? ', [$id]);
        if ($rbc) {
          $status = 200;
          $type = "FOLDER_NOT_EMPTY_NOT_DELETED";
          $logArr = [ "owner" => $tokenData['uid'],  "folder_id" => $args['uid'], "folder_name" => $name ];
          $resArr = [ "name" => $name, "type" => $type ];
        } else {
          $folderPath = $getPath.'/'.$slug;
          $sc = rmdir($folderPath);

          if ($sc) {
            R::trash( $rb );
          }          

          $success = true;
          $status = 200;
          $type = "FOLDER_DELETED";
          $logArr = [ "owner" => $tokenData['uid'],  "folder_id" => $args['uid'], "folder_name" => $name ];
          $resArr = [ "name" => $name, "type" => $type ];
        }
      } elseif ($type === 'image') {      
        foreach ($sizeArr as $size){
          $filePath = $getPath.'/'.$size.$slug;
          unlink($filePath);
        }

        R::trash( $rb );

        $success = true;
        $status = 200;
        $type = "IMAGE_DELETED";
        $logArr = [ "owner" => $tokenData['uid'],  "image_id" => $args['uid'], "image_name" => $name ];
        $resArr = [ "name" => $name, "type" => $type ];
      } else {
        $filePath = $getPath.'/'.$slug;
        $sc = unlink($filePath);
        
        if ($sc) {
          R::trash( $rb );
        }

        $success = true;
        $status = 200;
        $type = "FILE_DELETED";
        $logArr = [ "owner" => $tokenData['uid'],  "file_id" => $args['uid'], "file_name" => $name ];
        $resArr = [ "name" => $name, "type" => $type ];
      }

      
    } else{
      $status = 200;
      $type = "FILEMANAGER_ITEM_NOT_DELETED";
      $logArr = [ "user_id" => $tokenData['uid'],  "block_id" => $args['uid'], ];
      $resArr = [ "uid" => $args['uid'] ];
    }    
    // Return response
    $this->logger->info($type, $logArr);
    $result = [ "success" => $success, "status" => $status, "type" => $type, "response" => $resArr ];
    return $this->respondWithData($response,$result,$status);
  }



  private function moveUploadedFile($directory, UploadedFile $uploadedFile)
  {
      $filePath = pathinfo($uploadedFile->getClientFilename());
      $name = $this->slugify($filePath['filename']);
      $ext = strtolower($filePath['extension']);
      $fileName = $this->getNewFilename($name, $ext);
      $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $fileName);
      return $fileName;
  }

  private function getNewFilename($name, $ext, $ver = 0) {
    if ($ver === 0) {
      $fileName = $name.'.'.$ext;
    } else {
      $pos = strripos($name, "-") + 1;
      $start = substr($name, 0, $pos);
      $end = substr($name, $pos);
      if (is_numeric($end)) {
        $fileName = $start.$ver.'.'.$ext;
      } else {
        $fileName = $name.'-'.$ver.'.'.$ext;
      }
    }
    
    $rb  = R::findOne( 'filemanager', 'type != ? AND slug LIKE ?', ['folder', $fileName]); 

    if(!empty($rb)) {
      $ver = $ver + 1;
      $fileName = $this->getNewFilename($name, $ext, $ver);
    }

    return $fileName;
  }

  public function folderExist(Request $request, Response $response) {
    $data = $request->getParsedBody();
    // $arr = $this->getFileData($data);
    $id = $this->getID($data); 
    $slug = $this->slugify($data['name']);
    $pid = $this->getPID($data) ? $this->getPID($data) : 0;

    $status = 500;
    $success = false;

    if ($id) {
      $rb  = R::findOne( 'filemanager', 'type = ? AND slug LIKE ? AND parent_id = ? AND id != ?', ['folder', $slug, $pid, $id]); 
    } else {
      $rb  = R::findOne( 'filemanager', 'type = ? AND slug LIKE ? AND parent_id = ?', ['folder', $slug, $pid]); 
    }

    if (empty($rb)) {
      $success = true;
      $status = 200;
      $type = "FOLDER_NOT_FOUND";
    } else {
      $success = true;
      $status = 200;
      $type = "FOLDER_FOUND";
    }
    
    // Return response
    $result = [ "success" => $success, "status" => $status, "type" => $type ];
    return $this->respondWithData($response,$result,$status);
  }

  private function getFileFolderArr($uid) {
    $result = false;
    $rb = R::findOne('filemanager', 'uid = ?', [$uid]);
    if (!empty($rb->id)) {
      $result = $rb; 
    } 
    return $result;
  }

  private function getID($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('filemanager', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function getRecByUid($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('filemanager', 'uid = ?', [$args['uid']]);
      if (!empty($rb->id)) {
        $result = $rb; 
      }
    }    
    return $result;
  }

  private function getPID($args) {
    $result = false;
    if (isset($args['puid'])) {
      $rb = R::findOne('filemanager', 'uid = ?', [$args['puid']]);
      if (!empty($rb->id)) {
        $result = $rb->id; 
      }
    }    
    return $result;
  }

  private function getPUID($id) {
    $result = 0;
    $arr = [ ':folder' => $id ];

    $sql = "SELECT m.uid as puid FROM filemanager f ";
    $sql .= "LEFT JOIN filemanager m ON f.parent_id = m.id ";
    $sql.= "WHERE f.id = :folder ";    
    $rb = R::getAll($sql, $arr); 

    if (!empty($rb[0]['puid'])) {
      $result = $rb[0]['puid']; 
    }

    return $result;
  }

  private function getFileData($args) {
    $result = false;
    if (isset($args['uid'])) {
      $rb = R::findOne('filemanager', 'uid = ?', [$args['uid']]);
      if (!empty($rb)) {
        $result = $rb; 
      }
    }    
    return $result;
  }

  private function  vFolder($data, $cArr = NULL) {    
    $param['name'] = [
      "value" => [
        "pid" => $data['parent_id'],
        "slug" => $data['slug'],
        "value" => isset($data['name'])? $data['name'] : '',
      ],
      "type" => [ 
        "isEmpty" => "Folder name is required",
        "isFolderNameExist" => "Folder name already exist"  
      ]
    ];
    return $this->validateData($param);
  }
}