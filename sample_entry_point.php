<?php

use canopy\util\Autoload;
use canopy\Visitor;
use canopy\Canopy;
use canopy\Forest;
use canopy\filesystem\Path;
use canopy\filesystem\Directory;
use canopy\request\Session;

include __DIR__.strtr('/lib/canopy/util/Autoload.php', '/', DIRECTORY_SEPARATOR);
$loader = new Autoload(__DIR__);
$loader->register('lib');
$forest = new Forest(new Visitor(), new Directory());
$forest->setResponseMode('develop');
//$forest->setResponseMode('publish');
$forest->setLog('../console.log');
$forest->setTemplateDirectory(new Directory('../templates'));
$forest->setErrorDocs(new Directory('../templates/error'));
$forest->showErrorPages = true;
Session::cookie('visitor', 0, '/', null, false, true);
$forest->startSession();
$forest->history->startRecording('history');
$forest->route->findAWay($forest->root->readJSON('../routes.json'));
$forest->respond();

?>