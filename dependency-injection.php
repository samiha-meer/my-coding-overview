<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ServerRequestInterface as Request;

use src\classes\JPDatabase;

// DIC configuration
$container = $app->getContainer();

// view renderer
$container['eView'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    $phpView = new Slim\Views\PhpRenderer($settings['template_path']);
    
    return $phpView;
};

$container['view'] = function ($c) {
    
    $settings = $c->get('settings')['renderer'];

// Register flash message
$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));
    return $logger;
};
$container['userlogger'] = function ($c) {
    $settings = $c->get('settings')['user_logger'];
    $userlogger = new Monolog\Logger($settings['name']);
    $userlogger->pushProcessor(new Monolog\Processor\UidProcessor());
    $userlogger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));
    return $userlogger;
};

// Error Handling Code Starts HERE
$container['notAllowedHandler'] = function ($c) {
    return function ($request, $response, $methods)  use ($c) {
        return $c['response']->withStatus(405)
            ->withHeader('Allow', implode(',', $methods))
            ->withHeader('Content-type','application/json')
            ->write(json_encode(['errors' => ['userMessage' => 'Hey, you are not authorized to access this method', 'code' => 405]]));
    };
};

$container['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        $baseuri = $settings = $c->get('settings')['renderer']['base_url'];
        return DRY::showResponsePage($c['view'], $response, 'page-not-found');
    };
};

$container['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) use ($c) {
        $c['logger']->error($exception);
        return $c['eView']->render($response, 'public/partial/exception.phtml', []);
    };
};

$container['phpErrorHandler'] = function ($container) {
    return $container['errorHandler'];
};
$container['db'] = function ($c) {
    return new JPDatabase($c->get('settings')['db']);
};

$GLOBALS['jobDB'] = new JPDatabase($container->get('settings')['db']);

$capsule = new Capsule();
if($container->get('settings')['sameDBMS']) {
    $capsule->addConnection($container->get('settings')['JRdb'], "JRDB");
}

if($container->get('settings')['sameDBMSForJPArchives']) {
    $capsule->addConnection($container->get('settings')['JRdb'], "JRDB");
}

$capsule->addConnection($container->get('settings')['db'], "default");
$capsule->setAsGlobal();
$capsule->bootEloquent();

$container['csrf'] = function ($c) {
    return new \Slim\Csrf\Guard;
};

