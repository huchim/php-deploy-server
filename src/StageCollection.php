<?php namespace Huchim;

class StageCollection {
    /**
     * @var \Huchim\Stage[]
     */
    public $items = [];

    public function fromConfig($config) {
        
    }
    
    public function add(Stage $stage) {
        $this->items[] = $stage;
    }
    
    /**
     * 
     * @return \Huchim\Stage[]
     */
    public function getItems() {
        return $this->items;
    }
    
    public function getByName($name) {
        foreach ($this->items as $stage) {
            if ($stage->getName() === $name) {
                return $stage;
            }
        }
        
        return null;
    }
}
