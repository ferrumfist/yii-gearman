<?php
namespace ferrumfist\yii\gearman;

use GearmanTask;

interface TaskInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param GearmanTask|null $task
     * @return mixed
     */
    public function execute(GearmanTask $task = null);
}
