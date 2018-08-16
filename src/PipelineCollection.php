<?php namespace Huchim;

class PipelineCollection {
    /**
     * @var Pipeline[]
     */
    public $items = [];
    
    public function add(Pipeline $pipeline) {
        $this->items[] = $pipeline;
    }
    
    public function getCount() {
        return count($this->items);
    }
    
    /**
     * 
     * @return \Huchim\Pipeline[]
     */
    public function getItems() {
        return $this->items;
    }
    
    /**
     * 
     * @param string $name
     * @return Pipeline
     */
    public function getByName($name) {
        foreach ($this->items as $pipeline) {
            if ($pipeline->getName() === $name) {
                return $pipeline;
            }
        }
        
        return null;
    }
    
    /**
     * 
     * @param string $ppName
     * @param string $stage
     * @return Stage
     */
    public function getByStageName($ppName, $stage) {
        $pipeline = $this->getByName($ppName);
        
        if ($pipeline === null) {
            return null;
        }
        
        return $pipeline->stages->getByName($stage);        
    }
}
