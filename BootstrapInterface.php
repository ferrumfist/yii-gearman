<?php
namespace ferrumfist\yii\gearman;

interface BootstrapInterface
{
    public function run(Application $application);
}
