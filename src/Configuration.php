<?php namespace Huchim;

abstract class Configuration {
    private static $pipelines = null;
    
    /**
     * 
     * @return PipelineCollection
     */
    public static function getPipelines() {
        if (static::$pipelines !== null) {
            return static::$pipelines;
        }
        
        static::$pipelines = new PipelineCollection();
        
        return static::$pipelines;
    }
}
