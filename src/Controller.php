<?php

namespace Huchim;

use Slim\Http\Response;
use Slim\Http\Request;

class Controller
{

    private $pipelines = "";

    public function deploy(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $timestamp = trim($args["timestamp"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        if ($stageInstance->isLocked($timestamp)) {
            // La operación no se puede ejecutar porque existe un bloqueo por alguna operación
            // diferente a la actual.
            return $response->withStatus(400);
        }

        /* @var $uploadedFiles \Psr\Http\Message\UploadedFileInterface[] */
        $uploadedFiles = $request->getUploadedFiles();

        if (count($uploadedFiles) === 0) {
            return $response->withStatus(400);
        }

        // "file" es el nombre, pero no debería asumirlo.
        $fileInputKey = array_keys($uploadedFiles)[0];
        $firstFileUpload = $uploadedFiles[$fileInputKey];
        $deployZipName = $stageInstance->fileWIthDeployPath("{$timestamp}_deploy.zip");

        if ($firstFileUpload->getError() !== \UPLOAD_ERR_OK) {
            return $response->withJson([
                        "code" => (int) $firstFileUpload->getError(),
                        "message" => "El archivo no pudo ser subido a la aplicación",
                            ], 500);
        }

        // read json file.
        try {
            $firstFileUpload->moveTo($deployZipName);
        } catch (\RuntimeException $ex) {
            return $response->withJson([
                        "code" => 0,
                        "message" => sprintf("Error al subir el archivo %s (5s)", $deployZipName, $fileInputKey),
                        "inner" => [
                            "code" => $ex->getCode(),
                            "message" => $ex->getMessage(),
                        ],
                            ], 500);
        }

        return $response->withStatus(200);
    }

    public function diff_post(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $timestamp = trim($args["timestamp"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        if ($stageInstance->isLocked($timestamp)) {
            // La operación no se puede ejecutar porque existe un bloqueo por alguna operación
            // diferente a la actual.
            return $response->withStatus(400);
        }

        $uploadedFiles = $request->getUploadedFiles();

        if (count($uploadedFiles) === 0) {
            return $response->withStatus(400);
        }

        // "file" es el nombre, pero no debería asumirlo.
        $fileInputKey = array_keys($uploadedFiles)[0];
        $firstFileUpload = $uploadedFiles[$fileInputKey];

        // read json file.
        $fileContent = $firstFileUpload->getStream()->getContents();
        $jsonList = \json_decode($fileContent, true);

        $clientFileList = new FileList();

        $clientFileList->loadFromArray($jsonList);

        $m = implode("\n", $stageInstance->diff("current", $clientFileList));

        $response
                ->withHeader("Content-Type", "text/plain")
                ->getBody()
                ->write($m);

        return $response;
    }

    public function diff(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $timestamp = trim($args["timestamp"]);
        $compareWith = trim($args["to"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        if ($stageInstance->isLocked($timestamp)) {
            // La operación no se puede ejecutar porque existe un bloqueo por alguna operación
            // diferente a la actual.
            return $response->withStatus(400);
        }

        $m = implode("\n", $stageInstance->diff($timestamp, $compareWith));

        $response
                ->withHeader("Content-Type", "text/plain")
                ->getBody()
                ->write($m);

        return $response;
    }

    public function timestamps(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        $m = implode("\n", $stageInstance->getTimestamps());

        $response
                ->withHeader("Content-Type", "text/plain")
                ->getBody()
                ->write($m);

        return $response;
    }

    public function snapshot(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        $commit = isset($query["commit"]) ? $query["commit"] : "";
        $timestamp = isset($args["timestamp"]) ? trim($args["timestamp"]) : time();
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $lock = trim($args["lock"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        if (!$stageInstance->isExclusive($lock)) {
            // La operación no se puede ejecutar porque existe un bloqueo por alguna operación
            // diferente a la actual.
            return $response->withStatus(400);
        }

        // Guardo una instantanea de cómo están los archivos en este momento.
        $stageInstance->snapshot($timestamp, $commit);

        return $response->withStatus(201);
    }

    public function upload(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $timestamp = trim($args["timestamp"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        if (!$stageInstance->isExclusive($timestamp)) {
            // La operación no se puede ejecutar porque existe un bloqueo por alguna operación
            // diferente a la actual.
            return $response->withStatus(400);
        }

        $uploadedFiles = $request->getUploadedFiles();

        if (count($uploadedFiles) === 0) {
            return $response->withStatus(400);
        }

        // "file" es el nombre, pero no debería asumirlo.
        $fileInputKey = array_keys($uploadedFiles)[0];
        $firstFileUpload = $uploadedFiles[$fileInputKey];

        $stageInstance->deploy($timestamp, $firstFileUpload);

        return $response->withStatus(201);
    }

    public function lock(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $timestamp = trim($args["timestamp"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        $results = $stageInstance->lock($timestamp);

        return $response->withJson([
                    "timestamp" => $timestamp,
                    "status" => $results,
        ]);
    }

    public function unlock(Request $request, Response $response, $args)
    {
        $pipeName = trim($args["host"]);
        $stageName = trim($args["stage"]);
        $timestamp = trim($args["timestamp"]);

        // Recuperar la información del despliegue.
        $stageInstance = $this->getPipelines()->getByStageName($pipeName, $stageName);

        if ($stageInstance === null) {
            return $response->withStatus(404);
        }

        if ($stageInstance->requireLogin()) {
            if (!$stageInstance->authorize($request)) {
                return $response->withStatus(401);
            }
        }

        $results = $stageInstance->unlock($timestamp);

        return $response->withJson([
                    "timestamp" => $timestamp,
                    "status" => $results,
        ]);
    }

    public function help(Request $request, Response $response, $args)
    {
        return $response->withJson([
                    "authors" => [
                        [
                            "name" => "Carlos Huchim Ahumada",
                            "email" => "info@huchim.com",
                        ]
                    ],
                    "status" => $this->getPipelines()->getCount(),
                    "version" => "0.0.1-beta"]);
    }

    public static function getAction($actionName)
    {
        return sprintf("%s:%s", self::class, $actionName);
    }

    /**
     *
     * @return PipelineCollection
     */
    private function getPipelines()
    {
        return Configuration::getPipelines();
    }

}
