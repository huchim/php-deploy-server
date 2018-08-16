<?php namespace Huchim;

class FileList {
    private $directoryBase = "";
    private $currentFileList = ["commit" => "", "files" => []];
    
    public function __construct($directoryPath = "") {
        $this->setDirectory($directoryPath);
    }
    
    public function compare(FileList $nextList) {
        $nextFileList = $nextList->getFileList();        
        $sourceList = $this->currentFileList;
        $diff = [];

        // Buscar los NUEVOS en target que no están en source
        foreach ($nextFileList as $fileKey => $fileValue) {
            if (array_key_exists($fileKey, $sourceList)) {
                continue;
            }

            $diff[] = "+ " . $fileValue["name"];
        }

        // Buscar todos los QUE NO EXISTEN en target.
        // Son los que han cambiado o la fecha o el nombre.
        foreach ($sourceList as $fileKey => $fileValue) {
            if (array_key_exists($fileKey, $nextFileList)) {
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
    
    public function getFileList() {
        return $this->currentFileList;
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

        $this->currentFileList = $this->createRecursiveFileList($this->directoryBase, $excludeList);
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
                $ft = filemtime($currentItem);
                $filename = str_replace($baseDir, "", $currentItem);
                $fileKey = md5($filename . "::" . $ft);
    
                $files[$fileKey] = [
                    "name" => $filename
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