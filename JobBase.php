<?php

namespace ferrumfist\yii\gearman;

abstract class JobBase extends \CComponent implements JobInterface
{
    protected $name;
    public $count=1;
    
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * @var $name string
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    public function init(){
        return true;
    }
    
    /**
     * @param \GearmanJob $job
     * @return \filsh\yii2\gearman\JobWorkload
     */
    protected function getWorkload(\GearmanJob $job)
    {
        $workload = null;
        if($data = $job->workload()) {
            $workload = unserialize($data);
        }
        return $workload;
    }
}