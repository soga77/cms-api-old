<?php

declare(strict_types=1);

use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
// use App\Controllers\EmailController;
use App\Controllers\PageController;

return function (App $app) { 

  $app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello api world!");
    return $response;
  });  

  $app->group('/admin', function (Group $group) {
    // $group->group('/pages', function (Group $group) {
    //   $group->post('/add', PageController::class . ':add');
    //   $group->post('/edit', PageController::class . ':edit');
    //   $group->delete('/delete/{uid}', PageController::class . ':delete');
    //   $group->get('/view/{uid}', PageController::class . ':view');
    //   $group->get('/list', PageController::class . ':list');
    //   $group->get('/copy/{uid}', PageController::class . ':copy');
    //   $group->post('/exist', PageController::class . ':exist');
    // });
    $group->group('/auth', function (Group $group) {
      $group->post('/login', UserAuth::class . ':login');
      $group->post('/logout', UserAuth::class . ':logout');
      $group->get('/user', UserAuth::class . ':user');
    });

    $group->group('/email-templates', function (Group $group) {
      $group->get('/item/{uid}', EmailTemplateController::class . ':item');
      $group->get('/items', EmailTemplateController::class . ':items');
      $group->get('/list', EmailTemplateController::class . ':list');
      $group->get('/versions/{uid}', EmailTemplateController::class . ':versions');
      $group->post('/add',  EmailTemplateController::class . ':add');
      $group->post('/edit',  EmailTemplateController::class . ':edit');
      $group->get('/duplicate/{uid}', EmailTemplateController::class . ':duplicate');
      $group->delete('/delete/{uid}',  EmailTemplateController::class . ':delete');      
      $group->post('/name-exist',  EmailTemplateController::class . ':nameExist');
    });

    $group->group('/blocks', function (Group $group) {
      $group->get('/item/{uid}', BlockController::class . ':item');
      $group->get('/items', BlockController::class . ':items');
      $group->get('/versions/{uid}', BlockController::class . ':versions');
      $group->post('/add',  BlockController::class . ':add');
      $group->post('/edit',  BlockController::class . ':edit');
      $group->get('/duplicate/{uid}', BlockController::class . ':duplicate');
      $group->delete('/delete/{uid}',  BlockController::class . ':delete');      
      $group->post('/name-exist',  BlockController::class . ':nameExist');
      $group->post('/alias-exist',  BlockController::class . ':aliasExist');
    });

    $group->group('/modules', function (Group $group) {
      $group->get('/item/{uid}', ModuleController::class . ':item');
      $group->get('/items', ModuleController::class . ':items');
      $group->post('/add',  ModuleController::class . ':add');
      $group->post('/edit',  ModuleController::class . ':edit');
      $group->delete('/delete/{uid}',  ModuleController::class . ':delete');      
      $group->post('/name-exist',  ModuleController::class . ':nameExist');
      $group->post('/alias-exist',  ModuleController::class . ':aliasExist');
    });

    $group->group('/module-permissions', function (Group $group) {
      $group->get('/item/{uid}', PermissionController::class . ':item');
      $group->get('/items/{uid}', PermissionController::class . ':items');
      $group->post('/add',  PermissionController::class . ':add');
      $group->post('/edit',  PermissionController::class . ':edit');
      $group->delete('/delete/{uid}',  PermissionController::class . ':delete');      
      $group->post('/alias-exist',  PermissionController::class . ':aliasExist');
    });

    $group->group('/module-notifications', function (Group $group) {
      $group->get('/item/{uid}', NotificationController::class . ':item');
      $group->get('/items/{uid}', NotificationController::class . ':items');
      $group->post('/add',  NotificationController::class . ':add');
      $group->post('/edit',  NotificationController::class . ':edit');
      $group->delete('/delete/{uid}',  NotificationController::class . ':delete');      
      $group->post('/alias-exist',  NotificationController::class . ':aliasExist');
    });

    $group->group('/roles', function (Group $group) {
      $group->get('/item/{uid}', RoleController::class . ':item');
      $group->get('/items', RoleController::class . ':items');
      $group->get('/list', RoleController::class . ':list');
      $group->get('/permissions', RoleController::class . ':permissions');
      $group->post('/add',  RoleController::class . ':add');
      $group->post('/edit',  RoleController::class . ':edit');
      $group->get('/duplicate/{uid}', RoleController::class . ':duplicate');
      $group->delete('/delete/{uid}',  RoleController::class . ':delete'); 
      $group->post('/name-exist',  RoleController::class . ':nameExist');     
      $group->post('/alias-exist',  RoleController::class . ':aliasExist');
    });

    $group->group('/users', function (Group $group) {
      // $group->get('/item/{uid}', UserController::class . ':item');
      $group->get('/items', UserController::class . ':items');
      // $group->get('/roles', UserController::class . ':roles');
      $group->post('/add',  UserController::class . ':add');
      // $group->post('/edit',  UserController::class . ':edit');
      // $group->delete('/delete/{uid}',  UserController::class . ':delete'); 
      $group->post('/email-exist',  UserController::class . ':emailExist');     
      // $group->post('/alias-exist',  UserController::class . ':aliasExist');
    });
  });

  $app->group('/site', function (Group $group) {
    $group->group('/page', function (Group $group) {
      $group->get('/{slug}', PageController::class . ':slug');
      $group->get('/content/{slug}', PageController::class . ':content');
      $group->post('/exist', PageController::class . ':exist');
    });
    $group->group('/user', function (Group $group) {
      $group->post('/register', UserController::class . ':register');
      $group->post('/login', UserController::class . ':login');
      $group->post('/exist', UserController::class . ':exist');
      $group->post('/logout', UserController::class . ':logout');
      $group->get('/', UserController::class . ':user');
    });
  });



  // $app->group('/email-template', function (Group $group) {
  //   $group->post('/add', EmailTemplateController::class . ':add');
  //   $group->post('/edit', EmailTemplateController::class . ':edit');
  //   $group->delete('/delete/{uid}', EmailTemplateController::class . ':delete');
  //   $group->get('/view/{uid}', EmailTemplateController::class . ':view');
  //   $group->get('/list', EmailTemplateController::class . ':list');
  //   $group->get('/copy/{uid}', EmailTemplateController::class . ':copy');
  //   $group->post('/exist', EmailTemplateController::class . ':exist');
  // });

  // $app->group('/module', function (Group $group) {
  //   $group->post('/add', RegisterModuleController::class . ':add');
  //   // $group->put('/edit', UserController::class . ':edit');
  //   // $group->delete('/delete/{uid}', UserController::class . ':delete');
  //   // $group->get('/view/{uid}', UserController::class . ':view');
  //   $group->get('/list', UserController::class . ':list');
  //   // $group->get('/copy/{uid}', UserController::class . ':copy');
  //   // $group->post('/exist', UserController::class . ':exist');
  // });

  // $app->group('/user', function (Group $group) {
  //   $group->post('/add', UserController::class . ':add');
  //   $group->put('/edit', UserController::class . ':edit');
  //   $group->delete('/delete/{uid}', UserController::class . ':delete');
  //   $group->get('/view/{uid}', UserController::class . ':view');
  //   $group->get('/list', UserController::class . ':list');
  //   $group->get('/copy/{uid}', UserController::class . ':copy');
  //   $group->post('/exist', UserController::class . ':exist');
  // });

  // $app->group('/role', function (Group $group) {
  //   $group->post('/add', UserController::class . ':add');
  //   $group->put('/edit', UserController::class . ':edit');
  //   $group->delete('/delete/{uid}', UserController::class . ':delete');
  //   $group->get('/view/{uid}', UserController::class . ':view');
  //   $group->get('/list', UserController::class . ':list');
  //   $group->get('/copy/{uid}', UserController::class . ':copy');
  //   $group->post('/exist', UserController::class . ':exist');
  // });

 
  
};