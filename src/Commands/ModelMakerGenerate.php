<?php namespace AwkwardIdeas\ModelMaker\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use AwkwardIdeas\ModelMaker\ModelMaker;

class ModelMakerGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modelmaker:generate {--from=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //Create Migration Files
        if ($this->option('from') != "") {
            $from = $this->option('from');
        } else {
            $from= $this->ask('What database do you want to create the models from?');
        }
        $this->comment("Building models from $from.");
        $this->comment(PHP_EOL.ModelMaker::GenerateModels($from).PHP_EOL);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('from', null, InputOption::VALUE_OPTIONAL, "Database to generate models from","")
        );
    }
}
