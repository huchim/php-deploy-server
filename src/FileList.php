<?php namespace Huchim;

class FileList {
    private $directoryBase = "";
    private $currentFileList = ["commit" => "", "files" => []];
    
    public function __construct($directoryPath = "") {
        $this->setDirectory($directoryPath);
    }
    
    public function compare(FileList $nextList) {
        try {
            $nextFileList = $nextList->getFileList();
        } catch (\Exception $ex) {
            throw new \Exception("No se pudo recuperar la lista a comparar", 0, $ex);
        }

        try {
            $sourceList = $this->getFileList();
        } catch (\Exception $ex) {
            throw new \Exception("No se pudo recuperar la lista inicial", 0, $ex);
        }
        
        $differenceList = [];
        $differenceHashMap = [];

        // Buscar los NUEVOS en target que no están en source
        foreach ($nextFileList as $fileKey => $fileValue) {
            $method = "+";
            $action = "CREATE";
            $currentCheckSum = $fileValue["CheckSum"];
            
            if (array_key_exists($fileKey, $sourceList)) {
                $compareCheckSum = $sourceList[$fileKey]["CheckSum"];
                
                if ($currentCheckSum === $compareCheckSum) {
                    continue;
                }

                // Ambos archivos están en el mismo lado, pero con contenidos distintos.
                $method = "*";
                $action = "UPDATE";
            }

            $differenceHashMap[] = implode(" ", [$method, $fileValue["Name"]]);
            $differenceList[] = implode(" ", [
                $method,
                $fileValue["Name"],
                $action
            ]);
        }

        // Buscar todos los QUE NO EXISTEN en target.
        // Son los que han cambiado o la fecha o el nombre.
        foreach ($sourceList as $fileKey => $fileValue) {
            $method = "+";
            $action = "DELETE";

            if (array_key_exists($fileKey, $nextFileList)) {
                continue;
            }

            // Si el mismo archivo ya existe en el arreglo, se considera entonces
            // que fue modificado.
            $k = "+ " . $fileValue["Name"];

            if (!in_array($k, $differenceHashMap)) {
                $differenceHashMap[] = implode(" ", [$method, $fileValue["Name"]]);
                $differenceList[] = implode(" ", [
                    $method,
                    $fileValue["Name"],
                    $action
                ]);
            }
        }

        return $differenceList;
    }
    
    public function getFileList() {
        if (!isset($this->currentFileList["files"])) {
            throw new \Exception("No se ha definido la lista de archivos. Utilice setFileList primero.");
        }
        
        $fl = [];
        
        foreach ($this->currentFileList["files"] as $f) {
            $fl[$f["Name"]] = $f;
        }

        return $fl;
    }
    
    public function setFileList($fileList) {
        $this->currentFileList = $fileList;
    }
    
    public function loadFromArray($fileList) {
        $this->currentFileList = $fileList;
    }
            
    public function loadFromFile($historyFile) {
        if (!file_exists($historyFile)) {
            $sourceRawText = \json_encode(["commit" => "", "files" => []]);
        } else {
            $sourceRawText = file_get_contents($historyFile);
        }

        $this->currentFileList = \json_decode($sourceRawText, true);
    }
    
    public function loadFromCurrent($excludeList = []) {
        if ($this->directoryBase === "") {
            throw new \Exception("Se requiere un directorio inicial.");
        }

        $this->currentFileList["files"] = $this->createRecursiveFileList($this->directoryBase, $excludeList, $this->directoryBase);
    }
    
    public function setDirectory($directoryPath) {
        $this->directoryBase = $directoryPath;
    }

    private function createRecursiveFileList($directoryPath, $excludes = [], $baseDir = "", $separator = DIRECTORY_SEPARATOR) {
        $fileSearchResults = scandir($directoryPath);
        $files = [];
    
        $fileSearchResults = $this->removeUnusedDiretories($fileSearchResults);
    
        foreach($fileSearchResults as $ff){
            if (in_array($ff, $excludes)) {
                continue;
            }
            
            $currentItem = $directoryPath.$separator.$ff;
            $isDirectory = is_dir($currentItem);
    
            if($isDirectory) {
                $directoryContent = $this->createRecursiveFileList($currentItem, $excludes, $baseDir);
                
                foreach ($directoryContent as $file) {
                    $files[] = $file;
                }
            } else {
                // $ft = filemtime($currentItem);
                $filename = str_replace($baseDir, "", $currentItem);
                $fileKey = md5_file($currentItem) .  "::" . md5($filename);
    
                $files[] = [
                    "Name" => $filename,
                    "CheckSum" => $fileKey,
                    "Action" => "NONE"
                ];
            }
        }
        
        return $files;
    }
    
    private function removeUnusedDiretories($searchResults) {
        unset($searchResults[array_search('.', $searchResults, true)]);
        unset($searchResults[array_search('..', $searchResults, true)]);
        
        return $searchResults;
    }
}