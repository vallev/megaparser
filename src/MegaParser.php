<?php

namespace parserbot\megaparser;

class MegaParser extends BaseParser{

    public $queue;
    public $max;

    public function __construct($options=[])
    {

        $this->delay = isset($options['delay'])?$options['delay']:1;
        $this->concurrency = isset($options['concurrency'])?$options['concurrency']:5;
        $this->max = isset($options['max'])?$options['max']:1;

        $this->queue = new \SplQueue();
        parent::__construct($options);
    }

    /*
     * Генератор заданий - должен делать yield Task
     *
     * @abstract
     *
     */
    public function taskGenerator()
    {
        return null;
    }

    /*
     * Проверка массива заданий на то, что есть незавершенные задания
     *
     * @param array         $tasks
     *
     */
    private function hasTasks($tasks)
    {
        foreach ($tasks as $task) {
            if (!$task->isCompleted()) {
                return true;
            }
        }

        return false;
    }

    /*
     * Основной цикл работы парсера
     *
     */
    private function loop()
    {

        $max = $this->max;
        $tasks = [];
        $i = 0;
        $generator = $this->taskGenerator();
        $generator->rewind();
        while(true) {
            $task = $generator->current();

            if ($task) {
                if ($i % $max === 0) {
                    $tasks = [];
                }
                $tasks[] = $task;
            }

            if ((($i % $max === ($max-1)) || !$task) && count($tasks)) {
                while(true) {
                    $tasks = $this->process($tasks);
                    if (!$this->hasTasks($tasks)) {
                        break;
                    }
                }
            }
            $i++;

            if (!$task) {
                break;
            }

            $generator->next();

        }

    }

    /*
     * Обработка пачки заданий
     *
     * @param array         $tasks
     *
     */

    private function process($tasks)
    {

        $this->addMessage(count($tasks), 'level7');
        //Не нужно загружать сразу все задачи, нужно делить их на части -
        $return_tasks = [];
        $concurrency_groups = self::splitTasks($tasks);

        // Нужно отсортировать задачи по конкурентности
        // Затем разделить на части - по конкурентности
        // И уже отдельные части резать на части = concurrency
        foreach ($concurrency_groups as $k=>$grouped_tasks) {

            $real_concurrency = $k;
            if ($k === 0) {
                $real_concurrency = $this->concurrency;
            }

            $chunks = array_chunk($grouped_tasks, $real_concurrency);
            foreach ($chunks as $chunk) {
                $start = microtime(true);
                $this->addMessage('Start download', 'level6');
                $this->addMessage(memory_get_usage(), 'level7');
                $downloaded_tasks = $this->downloadHtmlsRaw($chunk, $real_concurrency, $this->num_retries, $this->delay);
                $this->addMessage(memory_get_usage(), 'level7');
                $this->addMessage('End download' . (microtime(true)-$start), 'level6');

                $this->addMessage(memory_get_usage(), 'level7');
                // Получили - теперь нужно обработать
                // проходим по каждому заданию и обрабатываем
                // полученные в результате обработки задания складываем в return_tasks
                foreach($downloaded_tasks as $task) {
                    if ($task->isCompleted()) {
                        //$return_tasks[] = $task;
                        //Не храним задания
                    } else {
                        //
                        $task->complete();
                        $method = new \ReflectionMethod(get_class($this), 'process'.$task->getCallback());
                        if ($method->isGenerator()) {
                            foreach ($this->{'process'.$task->getCallback()}($task) as $return_task) {
                                $return_tasks[] = $return_task;
                            }
                        } else {
                            $processed = $this->{'process'.$task->getCallback()}($task);
                            if (is_array($processed)) {
                                $return_tasks = array_merge($processed, $return_tasks);
                            }
                        }

                        // Принудительно удалим всю информацию о задаче
                        $task->destroy();
                        unset($task);
                    }
                }

                $this->counter += count($return_tasks);
                if ($this->counter > $this->concurrency) {
                    sleep($this->delay);
                    $this->counter = 0;
                }
            }

        }

        return $return_tasks;
    }


    /*
     * Запуск парсера
     *
     */

    public function parse()
    {
        $this->init();
        $this->loop();
        $this->finish();
    }

    /*
     * Функция, запускаемая перед парсингом
     *
     * @abstract
     *
     */

    public function init()
    {

    }

    /*
     * Функция, запускаемая после парсинга
     *
     * @abstract
     *
     */

    public function finish()
    {

    }

}

?>