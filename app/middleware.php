<?php

declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;
use App\Middleware\JsonBodyParserMiddleware;
use Psr\Log\LoggerInterface;

return function(App $app) {
  $settings = $app->getContainer()->get('settings')['jwt'];
  $logger = $app->getContainer()->get(LoggerInterface::class);
  //JWT
  $app->add(new Tuupola\Middleware\JwtAuthentication([
    "ignore"=>["/assets/*", "/site/user/exist", "/site/user/login", "/site/page/*", "/site/user/register", "/admin/auth/login"],
    "secret"=> $settings['secret'],
    "secure" => false,
    "logger" => $logger,
    "error"=>function ($response,$arguments)
    {
      $statusCode = 401;
      $data["success"]= false;
      $data["response"]["type"]="TOKEN_NOT_FOUND";
      $data["response"]["message"]=$arguments["message"];
      $data["response"]["status_code"] = $statusCode;

      // return $response->withHeader("Content-type","application/json")
      //   ->getBody()->write(json_encode($data,JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
      $json = json_encode($data, JSON_PRETTY_PRINT);
      $response->getBody()->write($json);
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($statusCode);
    }
  ]));

  


  // $app->add(function (Request $request, RequestHandlerInterface $handler) {
  //   $response = $handler->handle($request);
  //   return $response
  //           ->withHeader('Access-Control-Allow-Origin', '*')
  //           ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
  //           ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
  // });
  
  

  // Setup for preflight dont change
  $app->options('/{routes:.+}', function (Request $request, Response $response, $args) {
    return $response;
  });
 
  //CORS
  $app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $routeContext = RouteContext::fromRequest($request);
    $routingResults = $routeContext->getRoutingResults();
    $methods = $routingResults->getAllowedMethods();
    $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

    $response = $handler->handle($request);

    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    $response = $response->withHeader('Access-Control-Allow-Methods', implode(',', $methods));
    // $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response = $response->withHeader('Access-Control-Allow-Headers', $requestHeaders);

    // Optional: Allow Ajax CORS requests with Authorization header
    $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');

    return $response;
  });

  // Converts json to an array and also sanitizes the values
  $app->add(new JsonBodyParserMiddleware());   
};