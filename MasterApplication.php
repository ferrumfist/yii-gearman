<?php
namespace ferrumfist\yii\gearman;

use Yii;
use ferrumfist\yii\gearman\Application;

declare(ticks = 1);
class MasterApplication
{
    /**
     * @var Application
     */
    private static $instance;
    
    private $gearmanComponent;
    private $fork;
    private $process;
    
    private $signalCommand;
    private $waitAllChildrenStop = false;

    //list PIDS of children
    private $childrenPids = [];
    
    /**
     * @param unknown $gearmanComponent
     * @param unknown $fork
     */
    public function __construct($gearmanComponent, $fork)
    {
        $this->gearmanComponent = $gearmanComponent;
        $this->fork = $fork;
    }
    
    public function start(){
    	//после команды "старт" создадим дочерний поток,
    	//который будет следить за воркерами
    	
    	$this->stop();
    	
    	$pid = pcntl_fork();
    	
    	$observeMaster = (bool)$pid == 0;
    	
    	if($observeMaster){
    		//этот процесс будет следить за дочерними
    		$this->getProcess()->setPid(getmypid());
    		
    		//создаем дочернии процессы-воркеры
    		$apps = $this->getApplication();
    		//$parent = true;
    		foreach ($apps as $app) {
    			$parent = $this->startApp($app);
    			
    			//чтобы дочерние больше не порождали процессы
    			if( !$parent )
    				return ;
    		}
    		
    		if( $parent ){
    			//вешаем сигналы на мастера
    			$this->signalHandle();
    			
    			$this->observe();
    		}
    	}
    	else{
    		//родитель ничего не делает
    		return true;
    	}
    }
    
    protected function startApp($app){
    	$parent = $this->runApplication($app);
    	
    	//save child pid in the parent's list
    	if( $parent )
    		$this->childrenPids[] = $app->getPid();
    	
    	return $parent;
    }
    
    public function stop(){
    	$process = $this->getProcess();
    	$process->stop();
    }
    
    /**
     * Остановка воркеров
     */
    protected function stopChildren(){
    	$app = $this->getApplication();
    	foreach ($app as $value) {
    		 
    		$process = $value->getProcess($value->workerId);
    		 
    		$process->stop();
    	}
    }
    
    public function restart(){
    	$this->stop();
    	$this->start();
    }
    
    protected function getApplication(){
    	$component = Yii::app()->getComponent($this->gearmanComponent);
    	return $component->getApplication();
    }
    
    protected function runApplication(Application $app){
    	return $app->run((bool)$this->fork);
    }
    
    protected function getStoppedChildren(){
    	$pids = [];
    	
    	$pid = pcntl_waitpid(-1, $status, WNOHANG);
    	 
    	// Пока есть завершенные дочерние процессы
    	while ($pid > 0) {
    		$pids[] = $pid;
    		 
    		$pid = pcntl_waitpid(-1, $status, WNOHANG);
    	}
    	 
    	return $pids;
    }
    
    protected function recoverChild($pids){
    	foreach($pids as $pid){
    		$app = $this->getAppByPid($pid);
    		 
    		$parent = $this->startApp($app);
    		
    		if( !$parent )
    			return false;
    	}
    	
    	return true;
    }
    
    protected function getAppByPid($pid){
    	$apps = $this->getApplication();
    	
    	foreach($apps as $app){
    		if( $pid == $app->getPid() )
    			return $app;
    	}
    	
    	return false;
    }
    
    /**
     * Ф-ция отслеживания команды
     */
    protected function observe(){
    	while(1){
    		sleep(5);
    		
    		if( $this->signalCommand == 'kill' ){
    			$this->waitAllChildrenStop = true;
    			$this->stopChildren();
    		}
    		
    		if( $this->signalCommand == 'signalChild' ){
    			$pids = $this->getStoppedChildren();
    			
    			//delete pids from list
    			$this->childrenPids = array_diff( $this->childrenPids, $pids );
    			
    			//there is no children
    			if( count($this->childrenPids) == 0 ){
    				exit(0);
    			}
    			
    			//if we do not wait when all cheldren stopped
    			if( !$this->waitAllChildrenStop ){
	    			$parent = $this->recoverChild($pids);
	    			
	    			if( !$parent )
	    				break;
    			}
    		}
    		
    		$this->signalCommand = '';
    	}
    }
    
    /**
     * @param Process $process
     * @return $this
     */
    public function setProcess(Process $process)
    {
    	$this->process = $process;
    	return $this;
    }
    
    /**
     * @return Process
     */
    public function getProcess(){
    	if (null === $this->process) {
    		$this->setProcess(new Process(new Config(), "MasterApplication"));
    	}
    	return $this->process;
    }
    
    protected function signalHandle(){
    	pcntl_signal(SIGTERM, [$this, "signalKill"]);
    	pcntl_signal(SIGINT, [$this, "signalKill"]);
    	pcntl_signal(SIGCHLD, [$this, "signalChild"]);
    }
    
    public function signalKill($signo){
    	$this->signalCommand = 'kill';
    }
    
    public function signalChild(){
    	$this->signalCommand = 'signalChild';
    }
}
