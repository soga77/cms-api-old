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
    $group->group('/pages', function (Group $group) {
      $group->post('/add', PageController::class . ':add');
      $group->post('/edit', PageController::class . ':edit');
      $group->delete('/delete/{uid}', PageController::class . ':delete');
      $group->get('/view/{uid}', PageController::class . ':view');
      $group->get('/list', PageController::class . ':list');
      $group->get('/copy/{uid}', PageController::class . ':copy');
      $group->post('/exist', PageController::class . ':exist');
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
  //   $group->post('/add', ModuleController::class . ':add');
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