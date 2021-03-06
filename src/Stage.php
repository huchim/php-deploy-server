<?php namespace Huchim;

use Psr\Http\Message\UploadedFileInterface;

class Stage {
    /**
     *
     * @var Pipeline 
     */
    private $pipeline = null;
    private $userList = [];
    private $config = ["name" => "", "description" => "", "deploy_path" => ""];

    public function __construct($name = "") {
        $this->setName($name);
    }
    
    public function diffFS($source, $target) {
        return $source->compare($target);
    }
    
    public function diff($greaterThan, $equalOrLessThan) {
        $source = is_string($greaterThan) ? $this->getSnapshotEqualThan($greaterThan) : $greaterThan;
        $target = is_string($equalOrLessThan) ? $this->getSnapshotEqualThan($equalOrLessThan) : $equalOrLessThan;

        return $source->compare($target);
    }
    
    public function getTimestamps() {
        $indexFileTpl = $this->getSnapshotIndexFile("*");
        $timestamps = [];
        $files = \glob($indexFileTpl) ;
        
        foreach ($files as $idxFile) {
            $timestamps[] = $this->getTimestampFromFile($idxFile);
        }
        
        \rsort($timestamps, \SORT_NUMERIC);

        return $timestamps;
    }
    
    public function getSnapshotEqualOrLessThan($timestamp) {
        $indexFile = $this->getSnapshotIndexFile($timestamp);
        $fileList = new FileList();

        if (file_exists($indexFile)) {
            // Cargo los archivos desde el archivo.
            $fileList->loadFromFile($indexFile);
        }

        return $fileList;
    }
    
    /**
     * 
     * @param int $timestamp
     * @return \Huchim\FileList
     */
    public function getSnapshotEqualThan($timestamp) {
        $indexFile = $this->getSnapshotIndexFile($timestamp);
        $fileList = new FileList();

        if (file_exists($indexFile)) {
            // Cargo los archivos desde el archivo.
            $fileList->loadFromFile($indexFile);
        }
        
        if ($timestamp === "current") {
            $fileList->setDirectory($this->getDeployPath());
            $fileList->loadFromCurrent($this->pipeline->getExcludes());
        }

        return $fileList;
    }

    public function getSnapshotCreatingIfNotExists($timestamp) {
        $indexFile = $this->getSnapshotIndexFile($timestamp);
        $fileList = new FileList();

        if (file_exists($indexFile)) {
            // Cargo los archivos desde el archivo.
            $fileList->loadFromFile($indexFile);
        } else {
            $fileList->loadFromCurrent();
        }

        return $fileList;
    }
    
    /**
     * Crea un listado de los archivos actuales.
     *
     * Solo se puede generar una instantanea cuando existe un bloqueo en progreso.
     * 
     * La marca de tiempo a usar será la del momento.
     * 
     * @param string $currentLock Especifica el bloqueo actual.
     */
    public function snapshot($currentLock, $commit = "") {
        // Crear un archivo donde se guardará la lista de archivos.
        $excludeList = $this->pipeline->getExcludes();
        $indexFile = $this->getSnapshotIndexFile($currentLock);
        $deployPath = $this->getDeployPath();
        $fileList = new FileList($deployPath);
        
        // Crear el listado.
        $fileList->loadFromCurrent($excludeList);
        
        $fileContent = \json_encode([
            "commit" => $commit,
            "files" => $fileList->getFileList(),
        ]);
        
        file_put_contents($indexFile, $fileContent);
    }
   
    public function setPipeline(Pipeline $pipeline) {
        $this->pipeline = $pipeline;
        
        return $this;
    }
    
    public function getPipeline() {
        return $this->pipeline;
    }

    public function setName($name) {
        $this->config["name"] = $name;
        
        return $this;
    }
    
    public function getName() {
        return $this->config["name"];
    }
    
    public function setDescription($description) {
        $this->config["description"] = $description;
        
        return $this;
    }
    
    public function getDescription() {
        return $this->config["description"];
    }
    
    public function setDeployPath($deployPath) {
        $this->config["deploy_path"] = $deployPath;
        
        return $this;
    }
    
    public function getDeployPath() {
        return $this->config["deploy_path"];
    }
    
    public function fileWIthDeployPath($file) {
        $deployPath = $this->getDeployPath();
        
        if (substr($deployPath, strlen($deployPath) -1) === DIRECTORY_SEPARATOR) {
            $deployPath = substr($deployPath, 0, strlen($deployPath) - 1);
        }
        
        if (substr($file, 0, 1) === DIRECTORY_SEPARATOR) {
            $file = substr($file, 1);
        }
        
        return $deployPath . DIRECTORY_SEPARATOR . $file;
    }
    
    public function addUser($user, $pwd) {
        $this->userList[$user] = $pwd;
        
        return $this;
    }

    public function authorize(\Psr\Http\Message\RequestInterface $request) {
        $authHeader = $request->getHeader("Authorization");
        
        if (is_array($authHeader) && count($authHeader) === 0) {
            return false;
        }
        
        list ($authType, $authToken) = explode(" ", $authHeader[0]);

        if ($authType !== "Basic") {
            return false;
        }

        list ($username, $pwd) = explode(":", base64_decode($authToken));

        if (!array_key_exists($username, $this->userList)) {
            return false;
        }

        return $this->userList[$username] === $pwd;
    }
    
    public function requireLogin() {
        return count($this->userList) > 0;
    }
    
    public function deploy($timestamp, UploadedFileInterface $zipFile) {
        
    }
    
    public function lock($timestamp, $workingDirectory = "") {
        // TODO: Manejar errores si suceden.
        $lockContent = $this->getCurrentLockTimestamp($workingDirectory);
        
        if ($lockContent !== "" && $lockContent !== $timestamp) {
            // Existe otro bloqueo.
            return "OPERATION_IN_PROGRESS";
        }

        $lockFile = $this->getLockFile();

        // TODO: Manejar errores si suceden.
        file_put_contents($lockFile, $timestamp);
        
        return "LOCKED";
    }
    
    public function unlock($timestamp, $workingDirectory = "") {
        // TODO: Manejar errores si suceden.
        $lockContent = $this->getCurrentLockTimestamp($workingDirectory);

        if ($lockContent === "") {
            return "UNLOCKED";
        }

        if ($lockContent === $timestamp) {
            $lockFile = $this->getLockFile();

            // TODO: Manejar errores si suceden.
            unlink($lockFile);
            
            return "UNLOCKED";
        }
        
        return "OPERATION_IN_PROGRESS";
    }
    
    public function isExclusive($timestamp) {
        $lockContent = $this->getCurrentLockTimestamp();

        // Existe un archivo de bloque y es diferente a la marca de tiempo especificada.
        return $lockContent === $timestamp;
    }

    public function isLocked($timestamp) {
        $lockContent = $this->getCurrentLockTimestamp();

        // Existe un archivo de bloque y es diferente a la marca de tiempo especificada.
        return $lockContent !== "" && $lockContent !== $timestamp;
    }
    
    private function getSnapshotIndexFile($timestamp, $workingDirectory = "") {
        $directory = $this->getWorkingDirectory($workingDirectory);
        $pipeName = $this->pipeline->getName();

        return sprintf("%s/%s_%s_%s.idx", $directory, $pipeName, $this->getName(), $timestamp);
    }
    
    private function getLockFile($workingDirectory = "") {
        $directory = $this->getWorkingDirectory($workingDirectory);
        $pipeName = $this->pipeline->getName();

        return sprintf("%s/%s_%s.lock", $directory, $pipeName, $this->getName());
    }

    private function getWorkingDirectory($workingDirectory = "") {
        return $workingDirectory !== "" ? $workingDirectory : $this->pipeline->getWorkingDirectory();
    }

    private function getCurrentLockTimestamp($workingDirectory = "") {
        $lockFile = $this->getLockFile($workingDirectory);
        
        if (!file_exists($lockFile)) {
            return "";
        }
        
        return file_get_contents($lockFile);
    }
    
    private function getTimestampFromFile($filename) {
        $replaces = explode("|", $this->getSnapshotIndexFile("|"));
        
        return intval(str_replace($replaces, "", $filename));
    }
}
