<?php
include "vendor/autoload.php";

if (file_exists("config.php")) {
    include "config.php";
}

$whitelist = array('127.0.0.1', '::1');
define("APP_DEBUG", in_array($_SERVER['REMOTE_ADDR'], $whitelist));

// Crear la aplicación.
$app = new \Slim\App();

$app->add(function ($request, $response, $next) {    
    if (!APP_DEBUG) {
        if ($request->getUri()->getScheme() !== "https") {
            // No acceder a la aplicación de manera simple.
            return $response->withStatus(404);
        }
    }

    $response = $next($request, $response);
    
    return $response;
});

$app->get("/", Huchim\Controller::getAction("help"));
$app->get("/{host}/{stage}/lock/{timestamp}", Huchim\Controller::getAction("lock"));
$app->get("/{host}/{stage}/timestamps", Huchim\Controller::getAction("timestamps"));
$app->get("/{host}/{stage}/unlock/{timestamp}", Huchim\Controller::getAction("unlock"));
$app->get("/{host}/{stage}/snapshot/{lock}[/{timestamp}]",  Huchim\Controller::getAction("snapshot"));
$app->get("/{host}/{stage}/diff/{timestamp}/{to}",  Huchim\Controller::getAction("diff"));
$app->post("/{host}/{stage}/upload/{timestamp}", Huchim\Controller::getAction("upload"));

$app->run();


// $app->get("/", Huchim\Controller::class . ":help");

/*

// Bloquea el proceso para evitar que haya cambios durante la implementación.
$app->get("/{host}/{stage}/lock/{timestamp}", function($request, $response, $args) use ($hosts) {
    $query = $request->getQueryParams();
    $hostKey = isset($args["host"]) ? trim($args["host"]) : "";
    $stageName = isset($args["stage"]) ? trim($args["stage"]) : "";
    $timestamp = isset($args["timestamp"]) ? trim($args["timestamp"]) : time();

    $statusCode = getRequestCode($hostKey, $stageName, "linux", $hosts);

    if ($statusCode !== 200) {
        return $response->withStatus($statusCode);
    }
    
    $c = file_put_contents("timestamps/{$hostKey}_{$stageName}.lock", $timestamp);
    
    return $response
            ->withHeader("Location", "/{$hostKey}/{$stageName}/unlock/{$timestamp}")
            ->withStatus(201);    
});

$app->post("/{host}/{stage}/upload/{timestamp}", function(Request $request, Response $response, $args) use ($hosts) {
    $hostKey = isset($args["host"]) ? trim($args["host"]) : "";
    $stageName = isset($args["stage"]) ? trim($args["stage"]) : "";
    $timestamp = isset($args["timestamp"]) ? trim($args["timestamp"]) : time();

    $statusCode = getRequestCode($hostKey, $stageName, "linux", $hosts);

    if ($statusCode !== 200) {
        return $response->withStatus($statusCode);
    }
    
    $uploadedFiles = $request->getUploadedFiles();
    
    if (count($uploadedFiles) === 0) {
        return $response->withStatus(400);
    }
    
    // "file" es el nombre, pero no debería asumirlo.
    $fileInputKey = array_keys($uploadedFiles)[0];
    $firstFileUpload = $uploadedFiles[$fileInputKey];
    $path = "timestamps/{$hostKey}_{$stageName}_{$timestamp}.zip";    
    
    // Subir.
    $firstFileUpload->moveTo($path);
    
    return $response->withStatus(201);    
});

$app->get("/{host}/{stage}/unlock/{timestamp}", function($request, $response, $args) use ($hosts) {
    $query = $request->getQueryParams();
    $hostKey = isset($args["host"]) ? trim($args["host"]) : "";
    $stageName = isset($args["stage"]) ? trim($args["stage"]) : "";
    $timestamp = isset($args["timestamp"]) ? trim($args["timestamp"]) : time();

    $statusCode = getRequestCode($hostKey, $stageName, "linux", $hosts);

    if ($statusCode !== 200) {
        return $response->withStatus($statusCode);
    }
    
    $c = unlink("timestamps/{$hostKey}_{$stageName}.lock");
    
    return $response
            ->withStatus(201);    
});

// Crea un listado de archivos actuales y los guarda con el nuevo nombre.
$app->get("/{host}/{stage}/create[/{timestamp}]", function($request, $response, $args) use ($hosts) {
    $query = $request->getQueryParams();
    $clientType = isset($query["client"]) ? $query["client"] : "linux";
    $hostKey = isset($args["host"]) ? trim($args["host"]) : "";
    $stageName = isset($args["stage"]) ? trim($args["stage"]) : "";
    $timestamp = isset($args["timestamp"]) ? trim($args["timestamp"]) : time();
    $commit = isset($query["commit"]) ? $query["commit"] : "";

    $statusCode = getRequestCode($hostKey, $stageName, $clientType, $hosts);

    if ($statusCode !== 200) {
        return $response->withStatus($statusCode);
    }
    
    if (file_exists("timestamps/{$hostKey}_{$stageName}.lock")) {
        $currentTimestamp = file_get_contents("timestamps/{$hostKey}_{$stageName}.lock");
        
        if ($currentTimestamp !== $timestamp) {        
            return $response->withStatus(503);
        }
    }
    
    $host = $hosts[$hostKey];
    $stage = $host["stages"][$stageName];
    $excludes = isset($host["exclude"]) ? $host["exclude"] : [];
    $users = isset($stage["users"]) ? $stage["users"] : [];
    
    if (count($users) > 0) {
        // Require Login
        $usr = $request->getServerParam("PHP_AUTH_USER", "");
        $pwd = $request->getServerParam("PHP_AUTH_PW", "");
        
        if (!in_array($usr, $users)) {
            return $response->withStatus(401);
        }
    }
    
    if (!isset($stage["deploy_path"])) {
        return $response->withStatus(500);
    }
    
    $deploy_path = $stage["deploy_path"];
    
    // Lista de archivos.
    $output = [
        "commit" => $commit,
        "files" => listFolderFiles($deploy_path, $excludes, $deploy_path)
    ];
    
    $outputString = \json_encode($output);
    $c = file_put_contents("timestamps/{$hostKey}_{$stageName}_{$timestamp}_changes.json", $outputString);

    return $response->withStatus(201);
    
});

// Muestra los cambios entre el timestamp y el actual.
$app->get("/{host}/{stage}/diff/{timestamp}", function($request, $response, $args) use ($hosts) {
    $query = $request->getQueryParams();
    $client = isset($query["client"]) ? $query["client"] : "linux";
    $hostname = isset($args["host"]) ? trim($args["host"]) : "";
    $stageName = isset($args["stage"]) ? trim($args["stage"]) : "";
    $timestamp = isset($args["timestamp"]) ? trim($args["timestamp"]) : time();

    $statusCode = getRequestCode($hostname, $stageName, $client, $hosts);

    if ($statusCode !== 200) {
        return $response->withStatus($statusCode);
    }

    $host = $hosts[$hostname];
    $stage = $host["stages"][$stageName];
    $excludes = isset($host["exclude"]) ? $host["exclude"] : [];
    $users = isset($stage["users"]) ? $stage["users"] : [];
    
    if (count($users) > 0) {
        // Require Login
        $usr = $request->getServerParam("PHP_AUTH_USER", "");
        $pwd = $request->getServerParam("PHP_AUTH_PW", "");
        
        if (!in_array($usr, $users)) {
            return $response->withStatus(401);
        }
    }
    
    if (!isset($stage["deploy_path"])) {
        return $response->withStatus(500);
    }
    
    $deploy_path = $stage["deploy_path"];
    
    // Elegir el archivo
    if (!file_exists("timestamps/{$hostname}_{$stageName}_{$timestamp}_changes.json")) {
        $sourceRawText = \json_encode(["files" => []]);
    } else {
        $sourceRawText = file_get_contents("timestamps/{$hostname}_{$stageName}_{$timestamp}_changes.json");
    }
    
    $sourceFileList = \json_decode($sourceRawText, true);
    
    
    $currentFileList = listFolderFiles($deploy_path, $excludes, $deploy_path);

    $m = implode("\n", compareFileList($sourceFileList["files"], $currentFileList));

    $response
            ->withHeader("Content-Type", "text/plain")
            ->getBody()
            ->write($m);
    
    return $response;    
});

$app->run();

function compareFileList($sourceList, $targetList) {
    $diff = [];

    // Buscar los NUEVOS en target que no están en source
    foreach ($targetList as $fileKey => $fileValue) {
        if (array_key_exists($fileKey, $sourceList)) {
            continue;
        }
        
        $diff[] = "+ " . $fileValue["name"];
    }
    
    // Buscar todos los QUE NO EXISTEN en target.
    // Son los que han cambiado o la fecha o el nombre.
    foreach ($sourceList as $fileKey => $fileValue) {
        if (array_key_exists($fileKey, $targetList)) {
            continue;
        }
        
        // Si el mismo archivo ya existe en el arreglo, se considera entonces
        // que fue modificado.
        $k = "+ " . $fileValue["name"];
        
        if (!in_array($k, $diff)) {
            $diff[] = "- " . $fileValue["name"];
        }
    }
    
    return $diff;
}

function listFolderFiles($dir, $excludes = [], $baseDir = "", $separator = DIRECTORY_SEPARATOR ){
    $ffs = scandir($dir);
    $files = [];

    unset($ffs[array_search('.', $ffs, true)]);
    unset($ffs[array_search('..', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1) {
        return $files;
    }

    foreach($ffs as $ff){
        if (in_array($ff, $excludes)) {
            continue;
        }
        
        $currentItem = $dir.$separator.$ff;
        $isDirectory = is_dir($currentItem);

        if ($isDirectory) {
            $directoryContent = listFolderFiles($currentItem, $excludes, $baseDir);
            
            foreach ($directoryContent as $file) {
                $files[] = $file;
            }
        } else {
            $ft = filemtime($currentItem);
            $filename = str_replace($baseDir, "", $currentItem);
            $fileKey = md5($filename . "::" . $ft);

            $files[$fileKey] = [
                "name" => $filename,
                // "modified" => $ft
            ];
        }
    }
    
    return $files;
}

function createFileInfo($fileInfo) {
    return implode("|", $fileInfo);
}

function getRequestCode($hostname, $stage, $client, $hosts) {
    if (!in_array($client, ["win", "linux"])) {
        return 400;
    }
    
    if ($hostname === "" || $stage === "") {
        return 400;
    }
    
    $host = $hosts[$hostname];

    if (!isset($host["stages"][$stage])) {
        return 404;
    }
    
    return 200;
}
 * 
 */