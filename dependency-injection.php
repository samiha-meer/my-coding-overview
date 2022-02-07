<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ServerRequestInterface as Request;
use src\Models\JP\JPCoreScript;
use src\Models\JP\JPSetting;
use src\Models\JP\JPCustomLink;
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
    // Setting default template layout by setting gloabl variables
    $phpView = new Slim\Views\PhpRenderer($settings['template_path']);
    // Create base paths array

    $customCssFiles=[];
    foreach(glob($settings['base_path'] . '\assets\css\custom-css\public\*.css') as $file) {
        $customCssFiles[] = $settings['base_url'].'assets/css/custom-css/public/'.basename($file);
    }
    
    $basepaths = [
        'customCssFiles' => $customCssFiles,
        'css' => $settings['base_url'] . 'assets/css/',
        'content_images' => $settings['base_url'] . 'assets/content-images/',
        'js' => $settings['base_url'] . 'assets/js/',
        'img' => $settings['base_url'] . 'assets/img/',
        'plugin' => $settings['base_url'] . 'assets/plugins/',
        'url' => $settings['base_url'],
        'title' => $c->get('settings')['title'],
        'public' => $settings['base_url'] . 'assets/public/',
        'JPCoreScripts' => JPCoreScript::all(),
    ];
    
    //if(!isset($_SESSION['JPSetting'])) {
        $_SESSION['JPSetting'] = JPSetting::getAll();
    //}
    
    $templateVariables = [
        'basepaths' => $basepaths,
        'header' => $phpView->fetch('public/layouts/header.phtml', $basepaths),
        'footer' => $phpView->fetch('public/layouts/footer.phtml', $basepaths),
        'content' => '',
        'jscript' => '',
        'currPath' => $c->get('request')->geturi()->getPath(),
        'nav' => $phpView->fetch('public/layouts/nav.phtml', $basepaths),
        'JPSetting' => $_SESSION['JPSetting'],
            
    ];
    // setter
    $phpView->setAttributes($templateVariables);
    return $phpView;
};

$container['adminView'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    // Setting default template layout by setting gloabl variables
    $phpView = new Slim\Views\PhpRenderer($settings['template_path']);
    // Create base paths array
    $customCssFiles=[];
    foreach(glob($settings['base_path'] . '\assets\css\custom-css\admin\*.css') as $file) {
        $customCssFiles[] = $settings['base_url'].'assets/css/custom-css/admin/'.basename($file);
    }
    $basepaths = [
        'customCssFiles' => $customCssFiles,
        'css' => $settings['base_url'] . 'assets/css/',
        'content_images' => $settings['base_url'] . 'assets/content-images/',
        'js' => $settings['base_url'] . 'assets/js/',
        'img' => $settings['base_url'] . 'assets/img/',
        'plugin' => $settings['base_url'] . 'assets/plugins/',
        'url' => $settings['base_url'],
        'title' => $c->get('settings')['title'],
        'fmpath' => $settings['base_url'].'modules/tinyfilemanager/tinyfilemanager.php', 
        
    ];
    
    $templateVariables = [
        'basepaths' => $basepaths,
        'header' => $phpView->fetch('admin/layouts/header.phtml', $basepaths),
        'footer' => $phpView->fetch('admin/layouts/footer.phtml', $basepaths),
        'currPath' => $c->get('request')->geturi()->getPath(),
        'content' => '',
        'jscript' => '',
        'nav' => $phpView->fetch('admin/layouts/nav.phtml', $basepaths),
    ];
    // setter
    $phpView->setAttributes($templateVariables);
    return $phpView;
};

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

//$checkProxyHeaders = true;
//$trustedProxies = ['10.0.0.1', '10.0.0.2'];
//$app->add(new RKA\Middleware\IpAddress($checkProxyHeaders, $trustedProxies));

$container['csrf'] = function ($c) {
    return new \Slim\Csrf\Guard;
};

