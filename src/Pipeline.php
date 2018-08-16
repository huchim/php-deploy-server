<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Huchim;

/**
 * Description of Pipeline
 *
 * @author DA227
 */
class Pipeline {
    private $config = [
        "name" => "",
        "description" => "",
        "excludes" => [],
        "workingDirectory" => "timestamps",
    ];

    /**
     *
     * @var StageCollection
     */
    public $stages = null;

    public function __construct($name = "") {
        $this->setName($name);
        $this->stages = new StageCollection();
    }
    
    public function setWorkingDirectory($name) {
        $this->config["workingDirectory"] = $name;
        
        return $this;
    }

    public function getWorkingDirectory() {
        return $this->config["workingDirectory"];
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
    
    public function getExcludes() {
        return $this->config["excludes"];
    }

    public function addExclude(string $excludeFileOrDirName) {
        $this->config["excludes"][] = $excludeFileOrDirName;
        
        return $this;
    }

    public function addStage($stage, $deployPath = false, $users = []) {
        if (is_string($stage)) {
            $cl = new Stage($stage);
            $cl->setDeployPath($deployPath);
            
            foreach ($users as $user) {
                $cl->addUser($user);
            }
        } else {
            $cl = $stage;
        }
        
        $this->stages->add(
            $cl->setPipeline($this)
        );
    }
    
    public function getStageByName($name) {
        return $this->stages->getByName($name);
    }
}
